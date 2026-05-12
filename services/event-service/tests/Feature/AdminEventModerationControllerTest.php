<?php

namespace Tests\Feature;

use App\Models\AgeRating;
use App\Models\Category;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminEventModerationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_pending_review_events_and_approve_one(): void
    {
        $this->fakeAdminAuth();
        [$category, $ageRating] = $this->createDependencies();

        $event = Event::query()->create([
            'title' => 'Pending Festival',
            'description' => 'Description',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 77,
            'status' => Event::STATUS_PENDING_REVIEW,
        ]);

        $this->withToken('admin-token')
            ->getJson('/api/admin/events?status=pending_review')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $event->id);

        $this->withToken('admin-token')
            ->patchJson("/api/admin/events/{$event->id}/moderation", [
                'decision' => 'approve',
                'note' => 'Approved for publication.',
            ])
            ->assertOk()
            ->assertJsonPath('event.status', Event::STATUS_PUBLISHED);
    }

    public function test_admin_dashboard_returns_platform_event_metrics(): void
    {
        $this->fakeAdminAuth();
        [$category, $ageRating] = $this->createDependencies();

        Event::query()->create([
            'title' => 'Published Event',
            'description' => null,
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 1,
            'status' => Event::STATUS_PUBLISHED,
        ]);

        Event::query()->create([
            'title' => 'Needs Review',
            'description' => null,
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 2,
            'status' => Event::STATUS_PENDING_REVIEW,
        ]);

        $this->withToken('admin-token')
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('metrics.events_total', 2)
            ->assertJsonPath('metrics.events_published', 1)
            ->assertJsonPath('metrics.events_pending_review', 1);
    }

    public function test_admin_can_search_events(): void
    {
        $this->fakeAdminAuth();
        [$category, $ageRating] = $this->createDependencies();

        Event::query()->create([
            'title' => 'Jazz Night',
            'description' => 'Late music program',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 1,
            'status' => Event::STATUS_PENDING_REVIEW,
        ]);

        Event::query()->create([
            'title' => 'Business Forum',
            'description' => 'Conference agenda',
            'poster_url' => null,
            'category_id' => $category->id,
            'age_rating_id' => $ageRating->id,
            'organizer_id' => 2,
            'status' => Event::STATUS_PENDING_REVIEW,
        ]);

        $this->withToken('admin-token')
            ->getJson('/api/admin/events?search=Jazz')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.title', 'Jazz Night');
    }

    private function fakeAdminAuth(): void
    {
        Http::preventStrayRequests();

        Http::fake(function (HttpRequest $request) {
            if ($request->url() === 'http://127.0.0.1:8000/api/me') {
                return Http::response([
                    'user' => [
                        'id' => 1,
                        'full_name' => 'Platform Admin',
                        'email' => 'admin@example.com',
                        'role' => [
                            'role' => 'admin',
                        ],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });
    }

    /**
     * @return array{0: Category, 1: AgeRating}
     */
    private function createDependencies(): array
    {
        $category = Category::query()->create([
            'name' => 'Concert',
            'slug' => 'concert',
        ]);

        $ageRating = AgeRating::query()->create([
            'label' => '16+',
            'min_age' => 16,
        ]);

        return [$category, $ageRating];
    }
}
