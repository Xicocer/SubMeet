<?php

namespace Tests\Feature;

use App\Ai\Agents\EventChatConciergeAgent;
use App\Services\AuthServiceClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class EventAssistantConversationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_and_returns_saved_conversations_for_authenticated_user(): void
    {
        $this->instance(AuthServiceClient::class, Mockery::mock(AuthServiceClient::class, function ($mock): void {
            $mock->shouldReceive('getCurrentUser')
                ->andReturn([
                    'id' => 55,
                    'full_name' => 'Вадим Нестеров',
                    'status' => 1,
                ]);
        }));

        DB::table('agent_conversations')->insert([
            'id' => 'conv-001',
            'user_id' => 55,
            'title' => 'Свидание на следующей неделе',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinute(),
        ]);

        DB::table('agent_conversation_messages')->insert([
            [
                'id' => 'msg-001',
                'conversation_id' => 'conv-001',
                'user_id' => 55,
                'agent' => EventChatConciergeAgent::class,
                'role' => 'user',
                'content' => 'На следующей неделе планирую свидание.',
                'attachments' => '[]',
                'tool_calls' => '[]',
                'tool_results' => '[]',
                'usage' => '[]',
                'meta' => '[]',
                'created_at' => now()->subMinutes(10),
                'updated_at' => now()->subMinutes(10),
            ],
            [
                'id' => 'msg-002',
                'conversation_id' => 'conv-001',
                'user_id' => 55,
                'agent' => EventChatConciergeAgent::class,
                'role' => 'assistant',
                'content' => 'Подобрал атмосферные варианты.',
                'attachments' => '[]',
                'tool_calls' => '[]',
                'tool_results' => '[]',
                'usage' => '[]',
                'meta' => json_encode([
                    'recommendations' => [
                        [
                            'id' => 7,
                            'title' => 'Вечерний концерт',
                        ],
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now()->subMinutes(9),
                'updated_at' => now()->subMinutes(9),
            ],
        ]);

        $this->withToken('test-token')
            ->getJson('/api/events/assistant/conversations')
            ->assertOk()
            ->assertJsonPath('items.0.id', 'conv-001')
            ->assertJsonPath('items.0.title', 'Свидание на следующей неделе');

        $this->withToken('test-token')
            ->getJson('/api/events/assistant/conversations/conv-001')
            ->assertOk()
            ->assertJsonPath('id', 'conv-001')
            ->assertJsonPath('messages.0.role', 'user')
            ->assertJsonPath('messages.1.role', 'assistant')
            ->assertJsonPath('messages.1.recommendations.0.id', 7)
            ->assertJsonPath('messages.1.recommendations.0.title', 'Вечерний концерт');
    }
    public function test_it_normalizes_legacy_prompt_content_in_saved_conversations(): void
    {
        $this->instance(AuthServiceClient::class, Mockery::mock(AuthServiceClient::class, function ($mock): void {
            $mock->shouldReceive('getCurrentUser')
                ->andReturn([
                    'id' => 55,
                    'full_name' => 'Р’Р°РґРёРј РќРµСЃС‚РµСЂРѕРІ',
                    'status' => 1,
                ]);
        }));

        $legacyPrompt = <<<'PROMPT'
Ответь пользователю и используй только доступные кандидаты.
Контекст:
{
  "current_user_request": "Куда можно сходить с друзьями?",
  "intent_label": "friend_group",
  "available_candidates": []
}
PROMPT;

        DB::table('agent_conversations')->insert([
            'id' => 'conv-legacy',
            'user_id' => 55,
            'title' => 'Ответь пользователю и используй только доступные кандидаты.',
            'created_at' => now()->subMinutes(15),
            'updated_at' => now()->subMinutes(5),
        ]);

        DB::table('agent_conversation_messages')->insert([
            [
                'id' => 'msg-legacy-user',
                'conversation_id' => 'conv-legacy',
                'user_id' => 55,
                'agent' => EventChatConciergeAgent::class,
                'role' => 'user',
                'content' => $legacyPrompt,
                'attachments' => '[]',
                'tool_calls' => '[]',
                'tool_results' => '[]',
                'usage' => '[]',
                'meta' => '[]',
                'created_at' => now()->subMinutes(15),
                'updated_at' => now()->subMinutes(15),
            ],
            [
                'id' => 'msg-legacy-assistant',
                'conversation_id' => 'conv-legacy',
                'user_id' => 55,
                'agent' => EventChatConciergeAgent::class,
                'role' => 'assistant',
                'content' => 'Подобрал несколько вариантов для компании друзей.',
                'attachments' => '[]',
                'tool_calls' => '[]',
                'tool_results' => '[]',
                'usage' => '[]',
                'meta' => '[]',
                'created_at' => now()->subMinutes(14),
                'updated_at' => now()->subMinutes(14),
            ],
        ]);

        $this->withToken('test-token')
            ->getJson('/api/events/assistant/conversations')
            ->assertOk()
            ->assertJsonPath('items.0.id', 'conv-legacy')
            ->assertJsonPath('items.0.title', 'Куда можно сходить с друзьями?');

        $this->withToken('test-token')
            ->getJson('/api/events/assistant/conversations/conv-legacy')
            ->assertOk()
            ->assertJsonPath('messages.0.content', 'Куда можно сходить с друзьями?');

        $this->assertDatabaseHas('agent_conversations', [
            'id' => 'conv-legacy',
            'title' => 'Куда можно сходить с друзьями?',
        ]);

        $this->assertDatabaseHas('agent_conversation_messages', [
            'id' => 'msg-legacy-user',
            'content' => 'Куда можно сходить с друзьями?',
        ]);
    }
}
