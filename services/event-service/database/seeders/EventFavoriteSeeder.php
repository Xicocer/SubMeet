<?php

namespace Database\Seeders;

use App\Models\EventFavorite;
use Illuminate\Database\Seeder;

class EventFavoriteSeeder extends Seeder
{
    public function run(): void
    {
        EventFavorite::query()->delete();

        $favorites = [
            ['user_id' => 1201, 'event_id' => 3102],
            ['user_id' => 1201, 'event_id' => 3103],
            ['user_id' => 1202, 'event_id' => 3101],
            ['user_id' => 1203, 'event_id' => 3103],
        ];

        foreach ($favorites as $favorite) {
            EventFavorite::query()->create($favorite);
        }
    }
}
