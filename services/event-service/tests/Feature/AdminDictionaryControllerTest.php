<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminDictionaryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_categories_tags_and_age_ratings(): void
    {
        $this->fakeAdminAuth();

        $categoryId = $this->withToken('admin-token')
            ->postJson('/api/admin/categories', [
                'name' => 'Festival',
            ])
            ->assertCreated()
            ->json('id');

        $this->withToken('admin-token')
            ->putJson("/api/admin/categories/{$categoryId}", [
                'name' => 'Festival Updated',
            ])
            ->assertOk()
            ->assertJsonPath('slug', 'festival-updated');

        $ageRatingId = $this->withToken('admin-token')
            ->postJson('/api/admin/age-ratings', [
                'label' => '18+',
                'min_age' => 18,
            ])
            ->assertCreated()
            ->json('id');

        $this->withToken('admin-token')
            ->getJson('/api/admin/age-ratings')
            ->assertOk()
            ->assertJsonPath('0.id', $ageRatingId);

        $tagId = $this->withToken('admin-token')
            ->postJson('/api/admin/tags', [
                'name' => 'open air',
            ])
            ->assertCreated()
            ->json('id');

        $this->withToken('admin-token')
            ->deleteJson("/api/admin/tags/{$tagId}")
            ->assertOk();
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
}
