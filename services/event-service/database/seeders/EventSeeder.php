<?php

namespace Database\Seeders;

use App\Models\AgeRating;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventFavorite;
use App\Models\EventSession;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        EventFavorite::query()->delete();
        EventSession::query()->delete();
        DB::table('event_tag')->delete();
        Tag::query()->delete();
        Event::query()->delete();

        $events = [
            [
                'id' => 3101,
                'title' => 'Ночной рок-концерт на крыше',
                'description' => 'Живой рок под открытым небом, световая сцена и ночной вид на город.',
                'poster_url' => 'https://picsum.photos/seed/submeet-demo-rock/1200/800',
                'category_slug' => 'concert',
                'age_rating_label' => '16+',
                'organizer_id' => 1101,
                'status' => Event::STATUS_PUBLISHED,
                'moderation_note' => null,
                'tags' => ['рок', 'крыша', 'живой звук', 'вечерний концерт'],
            ],
            [
                'id' => 3102,
                'title' => 'Большой весенний стендап',
                'description' => 'Сольные выступления и общий блок лучших комиков вечера.',
                'poster_url' => 'https://picsum.photos/seed/submeet-demo-standup/1200/800',
                'category_slug' => 'standup',
                'age_rating_label' => '18+',
                'organizer_id' => 1102,
                'status' => Event::STATUS_PUBLISHED,
                'moderation_note' => null,
                'tags' => ['стендап', 'комики', 'юмор', 'вечер с друзьями'],
            ],
            [
                'id' => 3103,
                'title' => 'Иммерсивный спектакль "Тишина сцены"',
                'description' => 'Камерный театральный опыт, где зритель оказывается внутри действия.',
                'poster_url' => 'https://picsum.photos/seed/submeet-demo-theater/1200/800',
                'category_slug' => 'theater',
                'age_rating_label' => '12+',
                'organizer_id' => 1101,
                'status' => Event::STATUS_PUBLISHED,
                'moderation_note' => null,
                'tags' => ['театр', 'иммерсивный', 'камерная сцена', 'свидание'],
            ],
            [
                'id' => 3104,
                'title' => 'Городская выставка цифрового искусства',
                'description' => 'Интерактивные инсталляции, digital-постеры и медиа-зона молодых художников.',
                'poster_url' => 'https://picsum.photos/seed/submeet-demo-exhibition/1200/800',
                'category_slug' => 'exhibition',
                'age_rating_label' => '6+',
                'organizer_id' => 1102,
                'status' => Event::STATUS_PUBLISHED,
                'moderation_note' => null,
                'tags' => ['выставка', 'digital art', 'инсталляции', 'семья'],
            ],
            [
                'id' => 3105,
                'title' => 'Джазовый вечер для двоих',
                'description' => 'Небольшой концерт с мягким светом, баром и спокойным джазовым лайвом.',
                'poster_url' => 'https://picsum.photos/seed/submeet-demo-jazz/1200/800',
                'category_slug' => 'concert',
                'age_rating_label' => '12+',
                'organizer_id' => 1101,
                'status' => Event::STATUS_DRAFT,
                'moderation_note' => 'Организатору отказано по текущему слоту площадки, событие осталось в черновиках.',
                'tags' => ['джаз', 'свидание', 'уютный вечер', 'live'],
            ],
            [
                'id' => 3106,
                'title' => 'Акустический квартирник для друзей',
                'description' => 'Черновик камерного концерта с акустикой, свечами и форматной рассадкой.',
                'poster_url' => 'https://picsum.photos/seed/submeet-demo-acoustic/1200/800',
                'category_slug' => 'concert',
                'age_rating_label' => '16+',
                'organizer_id' => 1101,
                'status' => Event::STATUS_PENDING_REVIEW,
                'moderation_note' => 'Событие ожидает проверки описания и подтверждения площадки.',
                'tags' => ['акустика', 'квартирник', 'камерный концерт'],
            ],
            [
                'id' => 3107,
                'title' => 'Фестиваль городского света',
                'description' => 'Масштабный open air, который был снят с публикации после смены концепции.',
                'poster_url' => 'https://picsum.photos/seed/submeet-demo-lightfest/1200/800',
                'category_slug' => 'festival',
                'age_rating_label' => '0+',
                'organizer_id' => 1102,
                'status' => Event::STATUS_CANCELLED,
                'moderation_note' => 'Организатор отменил событие после закрытия площадочного слота.',
                'tags' => ['фестиваль', 'open air', 'световое шоу'],
            ],
        ];

        foreach ($events as $item) {
            $category = Category::query()->where('slug', $item['category_slug'])->first();
            $ageRating = AgeRating::query()->where('label', $item['age_rating_label'])->first();

            if ($category === null || $ageRating === null) {
                throw new InvalidArgumentException('Missing category or age rating for demo events.');
            }

            $event = Event::query()->create([
                'id' => $item['id'],
                'title' => $item['title'],
                'description' => $item['description'],
                'poster_url' => $item['poster_url'],
                'category_id' => $category->id,
                'age_rating_id' => $ageRating->id,
                'organizer_id' => $item['organizer_id'],
                'status' => $item['status'],
                'moderation_note' => $item['moderation_note'],
                'moderated_at' => $item['status'] === Event::STATUS_PENDING_REVIEW ? null : now()->subDays(4),
            ]);

            $tagIds = collect($item['tags'])
                ->map(fn (string $tagName) => trim($tagName))
                ->filter()
                ->map(function (string $tagName): int {
                    $slug = Str::slug($tagName);

                    if ($slug === '') {
                        $slug = Str::lower(Str::replace(' ', '-', Str::squish($tagName)));
                    }

                    return Tag::query()->firstOrCreate(
                        ['slug' => $slug],
                        ['name' => Str::squish($tagName)],
                    )->id;
                })
                ->values()
                ->all();

            $event->tags()->sync($tagIds);
        }
    }
}
