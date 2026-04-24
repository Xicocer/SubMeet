<?php

namespace Database\Seeders;

use App\Models\AgeRating;
use App\Models\Category;
use App\Models\Event;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            [
                'title' => 'Ночной рок-концерт на крыше',
                'description' => 'Живой рок под открытым небом, световая сцена и ночной вид на город.',
                'poster_url' => 'https://picsum.photos/seed/submeet-rock/1200/800',
                'category_slug' => 'concert',
                'age_rating_label' => '16+',
                'organizer_id' => 1,
                'status' => Event::STATUS_PUBLISHED,
            ],
            [
                'title' => 'Большой весенний стендап',
                'description' => 'Сольные выступления и общий блок лучших комиков вечера.',
                'poster_url' => 'https://picsum.photos/seed/submeet-standup/1200/800',
                'category_slug' => 'standup',
                'age_rating_label' => '18+',
                'organizer_id' => 2,
                'status' => Event::STATUS_PUBLISHED,
            ],
            [
                'title' => 'Иммерсивный спектакль "Тишина сцены"',
                'description' => 'Камерный театральный опыт, где зритель оказывается внутри действия.',
                'poster_url' => 'https://picsum.photos/seed/submeet-theater/1200/800',
                'category_slug' => 'theater',
                'age_rating_label' => '12+',
                'organizer_id' => 1,
                'status' => Event::STATUS_PUBLISHED,
            ],
            [
                'title' => 'Городская выставка цифрового искусства',
                'description' => 'Интерактивные инсталляции, digital-постеры и медиазона молодых художников.',
                'poster_url' => 'https://picsum.photos/seed/submeet-exhibition/1200/800',
                'category_slug' => 'exhibition',
                'age_rating_label' => '6+',
                'organizer_id' => 3,
                'status' => Event::STATUS_PUBLISHED,
            ],
            [
                'title' => 'Летний фестиваль света',
                'description' => 'Пока готовится к публикации: площадки, шоу, музыка и вечерние проекции.',
                'poster_url' => 'https://picsum.photos/seed/submeet-festival/1200/800',
                'category_slug' => 'festival',
                'age_rating_label' => '0+',
                'organizer_id' => 3,
                'status' => Event::STATUS_DRAFT,
            ],
            [
                'title' => 'Акустический квартирник для партнеров',
                'description' => 'Небольшой камерный концерт, который пока находится в стадии подготовки.',
                'poster_url' => 'https://picsum.photos/seed/submeet-acoustic/1200/800',
                'category_slug' => 'concert',
                'age_rating_label' => '16+',
                'organizer_id' => 2,
                'status' => Event::STATUS_DRAFT,
            ],
        ];

        foreach ($events as $item) {
            $category = Category::query()->where('slug', $item['category_slug'])->first();
            $ageRating = AgeRating::query()->where('label', $item['age_rating_label'])->first();

            if (!$category || !$ageRating) {
                throw new InvalidArgumentException('Category or age rating for event seeding was not found.');
            }

            Event::query()->updateOrCreate(
                [
                    'title' => $item['title'],
                    'organizer_id' => $item['organizer_id'],
                ],
                [
                    'description' => $item['description'],
                    'poster_url' => $item['poster_url'],
                    'category_id' => $category->id,
                    'age_rating_id' => $ageRating->id,
                    'status' => $item['status'],
                ]
            );
        }
    }
}
