<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_categories_for_public_api(): void
    {
        Category::query()->create([
            'name' => 'Концерт',
            'slug' => 'concert',
        ]);

        Category::query()->create([
            'name' => 'Спектакль',
            'slug' => 'performance',
        ]);

        $response = $this->getJson('/api/categories');

        $response
            ->assertOk()
            ->assertJson([
                [
                    'id' => 1,
                    'name' => 'Концерт',
                    'slug' => 'concert',
                ],
                [
                    'id' => 2,
                    'name' => 'Спектакль',
                    'slug' => 'performance',
                ],
            ]);
    }
}
