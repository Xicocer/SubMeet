<?php

namespace Tests\Feature;

use App\Models\AgeRating;
use App\Models\Category;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
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
                'tags' => ['лето', 'open air', 'музыка'],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('event.title', 'Летний фестиваль')
            ->assertJsonPath('event.organizer_id', 77)
            ->assertJsonPath('event.status', Event::STATUS_PENDING_REVIEW)
            ->assertJsonPath('event.tags.0.name', 'лето');

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
                'tags' => ['обновление', 'премьера'],
            ])
            ->assertOk()
            ->assertJsonPath('event.title', 'Новое название')
            ->assertJsonPath('event.status', Event::STATUS_PENDING_REVIEW)
            ->assertJsonPath('event.tags.0.slug', 'obnovlenie');

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

    public function test_update_rejects_critical_changes_when_paid_tickets_exist(): void
    {
        [$category, $ageRating] = $this->createEventDependencies();

        $event = Event::query()->create([
            'title' => 'Original Title',
            'description' => 'Description',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 77,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        $this->fakeOrganizerAuth(77, [
            'eventImpacts' => [
                $event->id => $this->guardPayload(eventId: $event->id, confirmedBookingsCount: 2, confirmedTicketsCount: 4),
            ],
        ]);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->putJson("/api/organizer/events/{$event->id}", [
                'title' => 'Renamed Event',
                'description' => 'Updated description only',
                'poster_url' => 'https://example.com/poster.jpg',
                'category_id' => $category->id,
                'age_rating_id' => $ageRating->id,
                'status' => Event::STATUS_PUBLISHED,
                'tags' => ['live'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['event']);
    }

    public function test_destroy_rejects_event_cancellation_with_active_reservations(): void
    {
        [$category, $ageRating] = $this->createEventDependencies();

        $event = Event::query()->create([
            'title' => 'Spring Show',
            'description' => 'Description',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 77,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        $this->fakeOrganizerAuth(77, [
            'eventImpacts' => [
                $event->id => $this->guardPayload(eventId: $event->id, activeReservationsCount: 1, activeReservedTicketsCount: 2),
            ],
        ]);

        $this->withHeader('Authorization', 'Bearer organizer-token')
            ->deleteJson("/api/organizer/events/{$event->id}", [
                'status' => Event::STATUS_CANCELLED,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
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

    /**
     * @param  array{eventImpacts?: array<int, array<string, mixed>>}  $options
     */
    private function fakeOrganizerAuth(int $organizerId, array $options = []): void
    {
        $eventImpacts = $options['eventImpacts'] ?? [];

        Http::fake(function (Request $request) use ($organizerId, $eventImpacts) {
            $url = $request->url();

            if ($url === 'http://127.0.0.1:8000/api/me') {
                return Http::response([
                    'user' => [
                        'id' => $organizerId,
                        'full_name' => 'Event Organizer',
                        'email' => 'organizer@example.com',
                        'role' => [
                            'role' => 'organizer',
                        ],
                    ],
                ], 200);
            }

            if (preg_match('#^http://127\.0\.0\.1:8003/api/organizer/guards/events/(\d+)/booking-impact$#', $url, $matches) === 1) {
                $eventId = (int) $matches[1];

                return Http::response(
                    $eventImpacts[$eventId] ?? $this->guardPayload(eventId: $eventId),
                    200
                );
            }

            return Http::response([], 404);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function guardPayload(
        ?int $eventId = null,
        ?int $eventSessionId = null,
        int $confirmedBookingsCount = 0,
        int $confirmedTicketsCount = 0,
        int $activeReservationsCount = 0,
        int $activeReservedTicketsCount = 0,
    ): array {
        return [
            'event_id' => $eventId,
            'event_session_id' => $eventSessionId,
            'has_confirmed_bookings' => $confirmedBookingsCount > 0,
            'confirmed_bookings_count' => $confirmedBookingsCount,
            'confirmed_tickets_count' => $confirmedTicketsCount,
            'active_reservations_count' => $activeReservationsCount,
            'active_reserved_tickets_count' => $activeReservedTicketsCount,
            'latest_confirmed_at' => null,
        ];
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
