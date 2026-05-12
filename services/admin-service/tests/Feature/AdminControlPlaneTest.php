<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminControlPlaneTest extends TestCase
{
    public function test_dashboard_aggregates_metrics_from_owner_services(): void
    {
        $this->fakeServices();

        $this->withToken('admin-token')
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('metrics.users_total', 12)
            ->assertJsonPath('metrics.events_pending_review', 3)
            ->assertJsonPath('metrics.revenue_total', 12500.5)
            ->assertJsonPath('booking.recent_problem_payments.0.status', 'failed');
    }

    public function test_organizer_moderation_is_proxied_to_auth_service(): void
    {
        $this->fakeServices();

        $this->withToken('admin-token')
            ->patchJson('/api/organizers/22/moderation', [
                'status' => 'approved',
                'note' => 'Looks good.',
            ])
            ->assertOk()
            ->assertJsonPath('organizer.user_id', 22)
            ->assertJsonPath('organizer.moderation_status', 'approved');
    }

    public function test_dictionary_categories_are_proxied_to_event_service(): void
    {
        $this->fakeServices();

        $this->withToken('admin-token')
            ->getJson('/api/dictionaries/categories')
            ->assertOk()
            ->assertJsonPath('0.slug', 'concert');
    }

    public function test_incidents_are_proxied_to_booking_service(): void
    {
        $this->fakeServices();

        $this->withToken('admin-token')
            ->getJson('/api/incidents?search=Festival')
            ->assertOk()
            ->assertJsonPath('data.0.snapshot.event_title', 'Festival Heat');
    }

    private function fakeServices(): void
    {
        Http::preventStrayRequests();

        Http::fake(function (HttpRequest $request) {
            $url = $request->url();

            if ($url === 'http://127.0.0.1:8000/api/me') {
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

            if ($url === 'http://127.0.0.1:8000/api/admin/dashboard') {
                return Http::response([
                    'metrics' => [
                        'users_total' => 12,
                        'organizers_total' => 5,
                        'organizers_pending' => 2,
                    ],
                ], 200);
            }

            if ($url === 'http://127.0.0.1:8001/api/admin/dashboard') {
                return Http::response([
                    'metrics' => [
                        'events_total' => 17,
                        'events_pending_review' => 3,
                    ],
                ], 200);
            }

            if ($url === 'http://127.0.0.1:8003/api/admin/dashboard') {
                return Http::response([
                    'metrics' => [
                        'bookings_total' => 44,
                        'revenue_total' => 12500.5,
                    ],
                    'recent_problem_payments' => [
                        [
                            'id' => 7,
                            'status' => 'failed',
                        ],
                    ],
                ], 200);
            }

            if ($url === 'http://127.0.0.1:8003/api/admin/incidents?search=Festival') {
                return Http::response([
                    'current_page' => 1,
                    'data' => [
                        [
                            'id' => 14,
                            'status' => 'failed',
                            'snapshot' => [
                                'event_title' => 'Festival Heat',
                            ],
                        ],
                    ],
                    'last_page' => 1,
                    'per_page' => 20,
                    'total' => 1,
                ], 200);
            }

            if ($url === 'http://127.0.0.1:8000/api/admin/organizers/22/moderation') {
                return Http::response([
                    'organizer' => [
                        'user_id' => 22,
                        'moderation_status' => 'approved',
                    ],
                ], 200);
            }

            if ($url === 'http://127.0.0.1:8001/api/admin/categories') {
                return Http::response([
                    [
                        'id' => 1,
                        'name' => 'Concert',
                        'slug' => 'concert',
                    ],
                ], 200);
            }

            return Http::response([
                'message' => 'Unexpected request: ' . $url,
            ], 500);
        });
    }
}
