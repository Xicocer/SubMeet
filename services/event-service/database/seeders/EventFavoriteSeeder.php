<?php

namespace Database\Seeders;

use App\Models\EventFavorite;
use Illuminate\Database\Seeder;

class EventFavoriteSeeder extends Seeder
{
    public function run(): void
    {
        $favorites = [
            ['user_id' => 1201, 'event_id' => 3102],
            ['user_id' => 1201, 'event_id' => 3103],
            ['user_id' => 1202, 'event_id' => 3101],
        ];

        foreach ($favorites as $favorite) {
            EventFavorite::query()->updateOrCreate(
                [
                    'user_id' => $favorite['user_id'],
                    'event_id' => $favorite['event_id'],
                ],
                []
            );
        }
    }
}
