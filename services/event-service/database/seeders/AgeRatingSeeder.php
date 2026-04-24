<?php

namespace Database\Seeders;

use App\Models\AgeRating;
use Illuminate\Database\Seeder;

class AgeRatingSeeder extends Seeder
{
    public function run(): void
    {
        $ageRatings = [
            ['label' => '0+', 'min_age' => 0],
            ['label' => '6+', 'min_age' => 6],
            ['label' => '12+', 'min_age' => 12],
            ['label' => '16+', 'min_age' => 16],
            ['label' => '18+', 'min_age' => 18],
        ];

        foreach ($ageRatings as $ageRating) {
            AgeRating::query()->updateOrCreate(
                ['label' => $ageRating['label']],
                ['min_age' => $ageRating['min_age']]
            );
        }
    }
}
