<?php

namespace Tests\Feature;

use App\Models\AgeRating;
use App\Models\Category;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrganizerEventControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_event_for_authorized_organizer(): void
    {
        [$category, $ageRating] = $this->createEventDependencies();
        $this->fakeOrganizerAuth(77);

        $response = $this->withHeader('Authorization', 'Bearer organizer-token')
            ->postJson('/api/organizer/events', [
                'title' => 'Летний фестиваль',
                'description' => 'Главное событие сезона',
                'poster_url' => 'https://example.com/poster.jpg',
                'category_id' => $category->id,
                'age_rating_id' => $ageRating->id,
                'organizer_id' => 999,
                'status' => Event::STATUS_PUBLISHED,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('event.title', 'Летний фестиваль')
            ->assertJsonPath('event.organizer_id', 77)
            ->assertJsonPath('event.status', Event::STATUS_PUBLISHED);

        $this->assertDatabaseHas('events', [
            'title' => 'Летний фестиваль',
            'organizer_id' => 77,
        ]);
    }

    public function test_update_allows_editing_only_own_event(): void
    {
        [$category, $ageRating] = $this->createEventDependencies();
        $this->fakeOrganizerAuth(50);

        $event = Event::query()->create([
            'title' => 'Старое название',
            'description' => 'Описание',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 50,
            'status' => Event::STATUS_DRAFT,
        ]);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->putJson("/api/organizer/events/{$event->id}", [
                'title' => 'Новое название',
                'description' => 'Обновленное описание',
                'poster_url' => 'https://example.com/new.jpg',
                'category_id' => $category->id,
                'age_rating_id' => $ageRating->id,
                'status' => Event::STATUS_PUBLISHED,
            ])
            ->assertOk()
            ->assertJsonPath('event.title', 'Новое название')
            ->assertJsonPath('event.status', Event::STATUS_PUBLISHED);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'title' => 'Новое название',
            'organizer_id' => 50,
        ]);
    }

    public function test_update_returns_404_for_foreign_event(): void
    {
        [$category, $ageRating] = $this->createEventDependencies();
        $this->fakeOrganizerAuth(50);

        $event = Event::query()->create([
            'title' => 'Чужое мероприятие',
            'description' => 'Описание',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 999,
            'status' => Event::STATUS_DRAFT,
        ]);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->putJson("/api/organizer/events/{$event->id}", [
                'title' => 'Попытка редактирования',
                'description' => 'Обновленное описание',
                'poster_url' => 'https://example.com/new.jpg',
                'category_id' => $category->id,
                'age_rating_id' => $ageRating->id,
                'status' => Event::STATUS_PUBLISHED,
            ])
            ->assertNotFound();
    }

    public function test_destroy_changes_status_instead_of_deleting(): void
    {
        [$category, $ageRating] = $this->createEventDependencies();
        $this->fakeOrganizerAuth(77);

        $event = Event::query()->create([
            'title' => 'Осенний концерт',
            'description' => 'Описание',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 77,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->deleteJson("/api/organizer/events/{$event->id}", [
                'status' => Event::STATUS_ARCHIVED,
            ])
            ->assertOk()
            ->assertJsonPath('event.status', Event::STATUS_ARCHIVED);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => Event::STATUS_ARCHIVED,
        ]);
    }

    public function test_my_events_returns_only_current_organizer_events(): void
    {
        [$category, $ageRating] = $this->createEventDependencies();
        $this->fakeOrganizerAuth(77);

        Event::query()->create([
            'title' => 'Мой первый концерт',
            'description' => 'Описание',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 77,
            'status' => Event::STATUS_DRAFT,
        ]);

        Event::query()->create([
            'title' => 'Чужое событие',
            'description' => 'Описание',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 88,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->getJson('/api/organizer/events')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.title', 'Мой первый концерт')
            ->assertJsonPath('data.0.organizer_id', 77);
    }

    public function test_non_organizer_cannot_access_organizer_routes(): void
    {
        Http::fake([
            'http://127.0.0.1:8000/api/me' => Http::response([
                'user' => [
                    'id' => 10,
                    'full_name' => 'Simple User',
                    'email' => 'user@example.com',
                    'role' => [
                        'role' => 'user',
                    ],
                ],
            ], 200),
        ]);

        $this->withHeader('Authorization', 'Bearer user-token')
            ->getJson('/api/organizer/events')
            ->assertForbidden()
            ->assertJsonPath('message', 'Доступ разрешен только организаторам.');
    }

    private function fakeOrganizerAuth(int $organizerId): void
    {
        Http::fake([
            'http://127.0.0.1:8000/api/me' => Http::response([
                'user' => [
                    'id' => $organizerId,
                    'full_name' => 'Event Organizer',
                    'email' => 'organizer@example.com',
                    'role' => [
                        'role' => 'organizer',
                    ],
                ],
            ], 200),
        ]);
    }

    /**
     * @return array{0: Category, 1: AgeRating}
     */
    private function createEventDependencies(): array
    {
        $category = Category::query()->create([
            'name' => 'Концерт',
            'slug' => 'concert',
        ]);

        $ageRating = AgeRating::query()->create([
            'label' => '16+',
            'min_age' => 16,
        ]);

        return [$category, $ageRating];
    }
}
