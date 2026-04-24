<?php

namespace Tests\Feature;

use App\Models\AgeRating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgeRatingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_age_ratings_for_filters(): void
    {
        AgeRating::query()->create([
            'label' => '12+',
            'min_age' => 12,
        ]);

        AgeRating::query()->create([
            'label' => '16+',
            'min_age' => 16,
        ]);

        $response = $this->getJson('/api/age-ratings');

        $response
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.label', '12+')
            ->assertJsonPath('1.min_age', 16);
    }
}
