<?php

namespace Tests\Feature;

use App\Services\AuthServiceClient;
use App\Services\EventAssistantCandidateResolver;
use App\Services\EventConciergeNarrator;
use App\Services\EventDiscoveryQueryAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class EventAssistantChatControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_streams_fallback_response_and_persists_history_for_authenticated_user(): void
    {
        config([
            'services.concierge.provider' => 'openai',
            'ai.providers.openai.key' => null,
        ]);

        $this->instance(AuthServiceClient::class, Mockery::mock(AuthServiceClient::class, function ($mock): void {
            $mock->shouldReceive('getCurrentUser')
                ->andReturn([
                    'id' => 55,
                    'full_name' => 'Вадим Нестеров',
                    'status' => 1,
                ]);
        }));

        $this->instance(EventDiscoveryQueryAnalyzer::class, Mockery::mock(EventDiscoveryQueryAnalyzer::class, function ($mock): void {
            $mock->shouldReceive('analyze')
                ->once()
                ->with('Куда сходить сегодня?')
                ->andReturn([
                    'intent_label' => 'today',
                    'timeframe_label' => 'сегодня',
                    'preferred_categories' => ['concert'],
                    'preferred_tags' => [],
                    'reference_date' => now()->toDateString(),
                ]);
        }));

        $this->instance(EventAssistantCandidateResolver::class, Mockery::mock(EventAssistantCandidateResolver::class, function ($mock): void {
            $mock->shouldReceive('resolve')
                ->once()
                ->andReturn([
                    'candidate_source' => 'catalog_fallback',
                    'items' => [
                        [
                            'id' => 7,
                            'title' => 'Ночной концерт',
                            'category' => 'concert',
                            'category_name' => 'Концерт',
                            'event_date' => now()->toDateString(),
                            'price' => 1800,
                            'age_rating' => 12,
                            'city' => 'Нижний Новгород',
                            'venue_address' => 'ул. Рождественская, 7',
                        ],
                    ],
                ]);
        }));

        $this->instance(EventConciergeNarrator::class, Mockery::mock(EventConciergeNarrator::class, function ($mock): void {
            $mock->shouldReceive('narrate')
                ->once()
                ->andReturn([
                    'mode' => 'fallback',
                    'answer' => 'Подобрал для вас живой концерт на сегодня.',
                    'items' => [],
                ]);
        }));

        $response = $this
            ->withToken('test-token')
            ->postJson('/api/events/assistant/chat/stream', [
                'message' => 'Куда сходить сегодня?',
            ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');

        $stream = $response->streamedContent();

        $this->assertStringContainsString('event: meta', $stream);
        $this->assertStringContainsString('event: text-delta', $stream);
        $this->assertStringContainsString('event: recommendations', $stream);
        $this->assertStringContainsString('event: done', $stream);

        $this->assertDatabaseHas('agent_conversations', [
            'user_id' => 55,
            'title' => 'Куда сходить сегодня?',
        ]);

        $this->assertDatabaseCount('agent_conversation_messages', 2);
        $this->assertDatabaseHas('agent_conversation_messages', [
            'user_id' => 55,
            'role' => 'user',
            'content' => 'Куда сходить сегодня?',
        ]);
        $this->assertDatabaseHas('agent_conversation_messages', [
            'user_id' => 55,
            'role' => 'assistant',
            'content' => 'Подобрал для вас живой концерт на сегодня.',
        ]);
    }
}
