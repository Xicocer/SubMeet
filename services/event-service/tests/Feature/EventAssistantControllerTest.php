<?php

namespace Tests\Feature;

use App\Services\EventDiscoveryAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class EventAssistantControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_assistant_payload(): void
    {
        $this->instance(EventDiscoveryAssistant::class, Mockery::mock(EventDiscoveryAssistant::class, function ($mock): void {
            $mock->shouldReceive('reply')
                ->once()
                ->with('Куда сходить сегодня?', null, 4)
                ->andReturn([
                    'query' => 'Куда сходить сегодня?',
                    'answer' => 'Подобрал несколько живых событий на сегодня.',
                    'mode' => 'fallback',
                    'candidate_source' => 'catalog_fallback',
                    'personalized' => false,
                    'context' => [
                        'intent_label' => null,
                        'timeframe_label' => 'сегодня',
                    ],
                    'items' => [
                        [
                            'id' => 7,
                            'title' => 'Вечерний концерт',
                            'category' => 'concert',
                            'category_name' => 'Концерт',
                            'assistant_note' => 'Ближайший концерт с вечерним сеансом.',
                        ],
                    ],
                ]);
        }));

        $this->postJson('/api/events/assistant', [
            'query' => 'Куда сходить сегодня?',
        ])
            ->assertOk()
            ->assertJsonPath('answer', 'Подобрал несколько живых событий на сегодня.')
            ->assertJsonPath('context.timeframe_label', 'сегодня')
            ->assertJsonPath('items.0.id', 7)
            ->assertJsonPath('items.0.assistant_note', 'Ближайший концерт с вечерним сеансом.');
    }

    public function test_it_validates_query(): void
    {
        $this->postJson('/api/events/assistant', [
            'query' => 'no',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['query']);
    }
}
