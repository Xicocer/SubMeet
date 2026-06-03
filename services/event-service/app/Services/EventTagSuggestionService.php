<?php

namespace App\Services;

use App\Ai\Agents\EventTagSuggestionTextAgent;
use App\Models\Tag;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class EventTagSuggestionService
{
    private const MAX_TAGS = 8;
    private const MAX_TAG_LENGTH = 30;

    /**
     * @param  array<int, string>  $alreadySelectedTags
     * @return array{tags: array<int, array{name: string, exists: bool}>, mode: string}
     */
    public function suggest(
        ?string $title,
        ?string $description = null,
        ?string $categoryName = null,
        ?string $ageRatingLabel = null,
        array $alreadySelectedTags = [],
    ): array {
        $rawTags = [];
        $mode = 'fallback';

        if ($this->isConfigured()) {
            try {
                $response = (new EventTagSuggestionTextAgent)->prompt(
                    $this->buildPrompt($title, $description, $categoryName, $ageRatingLabel, $alreadySelectedTags),
                    provider: config('services.concierge.provider'),
                    model: config('services.concierge.model') ?: null,
                    timeout: (int) config('services.concierge.timeout', 30),
                );

                $rawTags = $this->parseAiTags($response->text);
                $mode = $rawTags !== [] ? 'ai' : 'fallback';

                if ($rawTags === []) {
                    Log::warning('event_tag_suggestion_invalid_ai_payload', [
                        'provider' => config('services.concierge.provider'),
                        'model' => config('services.concierge.model'),
                        'preview' => Str::limit($response->text, 500),
                    ]);
                }
            } catch (Throwable $exception) {
                Log::warning('event_tag_suggestion_ai_failed', [
                    'provider' => config('services.concierge.provider'),
                    'model' => config('services.concierge.model'),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($rawTags === []) {
            $rawTags = $this->fallbackTags($title, $description, $categoryName, $ageRatingLabel);
            $mode = 'fallback';
        }

        $selected = collect($alreadySelectedTags)
            ->map(fn (string $tag): ?string => $this->normalizeTag($tag))
            ->filter()
            ->values()
            ->all();

        $tags = collect($rawTags)
            ->map(fn (mixed $tag): ?string => $this->extractTagName($tag))
            ->map(fn (?string $tag): ?string => $tag === null ? null : $this->normalizeTag($tag))
            ->filter()
            ->unique()
            ->reject(fn (string $tag): bool => in_array($tag, $selected, true))
            ->take(self::MAX_TAGS)
            ->values()
            ->all();

        return [
            'tags' => $this->withExistingFlag($tags),
            'mode' => $mode,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function parseAiTags(string $text): array
    {
        $cleanText = trim($text);
        $cleanText = preg_replace('/^```(?:json)?\s*/i', '', $cleanText) ?? $cleanText;
        $cleanText = preg_replace('/\s*```$/', '', $cleanText) ?? $cleanText;

        $decoded = json_decode($cleanText, true);

        if (! is_array($decoded) && preg_match('/\{.*\}/s', $cleanText, $matches) === 1) {
            $decoded = json_decode($matches[0], true);
        }

        if (! is_array($decoded)) {
            return [];
        }

        if (array_is_list($decoded)) {
            return $decoded;
        }

        $tags = Arr::get($decoded, 'tags', []);

        if (! is_array($tags)) {
            return [];
        }

        return $tags;
    }

    private function extractTagName(mixed $tag): ?string
    {
        if (is_string($tag)) {
            return $tag;
        }

        if (is_array($tag) && is_string($tag['name'] ?? null)) {
            return $tag['name'];
        }

        return null;
    }

    private function normalizeTag(string $tag): ?string
    {
        $tag = Str::of($tag)
            ->lower()
            ->replace(['#', '"', "'", '`', '«', '»'], '')
            ->replaceMatches('/[^\p{L}\p{N}\s+\-]+/u', ' ')
            ->squish()
            ->toString();

        if ($tag === '' || mb_strlen($tag) < 2 || mb_strlen($tag) > self::MAX_TAG_LENGTH) {
            return null;
        }

        if ($this->isForbiddenTag($tag)) {
            return null;
        }

        return $tag;
    }

    private function isForbiddenTag(string $tag): bool
    {
        $forbiddenParts = [
            'хуй',
            'пизд',
            'еба',
            'ебл',
            'бля',
            'сука',
            'fuck',
            'shit',
            'nsfw',
            'казино',
            'спам',
            'наркот',
        ];

        return Str::contains($tag, $forbiddenParts);
    }

    /**
     * @return array<int, string>
     */
    private function fallbackTags(
        ?string $title,
        ?string $description,
        ?string $categoryName,
        ?string $ageRatingLabel,
    ): array {
        $text = Str::lower(Str::squish(implode(' ', array_filter([
            $title,
            $description,
            $categoryName,
            $ageRatingLabel,
        ]))));

        $tags = [];

        if ($categoryName !== null && trim($categoryName) !== '') {
            $tags[] = $categoryName;
        }

        $keywordMap = [
            'концерт' => ['концерт', 'музык', 'рок', 'джаз', 'группа', 'вокал', 'live', 'акустик'],
            'стендап' => ['стендап', 'standup', 'комик', 'юмор', 'квн', 'шутк'],
            'театр' => ['театр', 'спектак', 'сцена', 'актер', 'иммерсив'],
            'фестиваль' => ['фестивал', 'open air', 'опен эйр', 'уличн'],
            'выставка' => ['выставк', 'арт', 'искусств', 'галере', 'экспозиц'],
            'лекция' => ['лекци', 'обуч', 'мастер-класс', 'встреча', 'разговор'],
            'свидание' => ['свидан', 'романтик', 'для двоих', 'вечер для двоих'],
            'для друзей' => ['друз', 'компани', 'вечерин', 'энергич'],
            'вечер' => ['вечер', 'ночн', 'после работы'],
            'семейное' => ['семейн', 'дет', 'родител'],
            'необычное' => ['необыч', 'эксперимент', 'immersive', 'иммерсив'],
            'танцы' => ['танц', 'dj', 'диджей', 'dance'],
            'живая музыка' => ['живая музыка', 'live', 'акустик', 'музыкант'],
        ];

        foreach ($keywordMap as $tag => $needles) {
            if (Str::contains($text, $needles)) {
                $tags[] = $tag;
            }
        }

        if ($tags === []) {
            $tags = ['афиша', 'вечер', 'событие'];
        }

        return collect($tags)
            ->merge(['афиша', 'вечер', 'событие'])
            ->unique()
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $tags
     * @return array<int, array{name: string, exists: bool}>
     */
    private function withExistingFlag(array $tags): array
    {
        $slugsByTag = collect($tags)
            ->mapWithKeys(fn (string $tag): array => [$tag => $this->slugForTag($tag)]);

        $existingSlugs = Tag::query()
            ->whereIn('slug', $slugsByTag->values()->all())
            ->pluck('slug')
            ->all();

        $existingSlugSet = array_flip($existingSlugs);

        return $slugsByTag
            ->map(fn (string $slug, string $tag): array => [
                'name' => $tag,
                'exists' => isset($existingSlugSet[$slug]),
            ])
            ->values()
            ->all();
    }

    private function slugForTag(string $tag): string
    {
        $slug = Str::slug($tag);

        if ($slug === '') {
            $slug = Str::lower(Str::replace(' ', '-', Str::squish($tag)));
        }

        return $slug;
    }

    /**
     * @param  array<int, string>  $alreadySelectedTags
     */
    private function buildPrompt(
        ?string $title,
        ?string $description,
        ?string $categoryName,
        ?string $ageRatingLabel,
        array $alreadySelectedTags,
    ): string {
        return "Suggest 5-8 tags for this event. Return only JSON.\n"
            . json_encode([
                'title' => $title,
                'description' => $description,
                'category' => $categoryName,
                'age_rating' => $ageRatingLabel,
                'already_selected_tags' => array_values($alreadySelectedTags),
                'tag_rules' => [
                    'lowercase',
                    'max 30 characters',
                    'no duplicates',
                    'search-friendly',
                    'do not repeat already selected tags',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function isConfigured(): bool
    {
        $provider = (string) config('services.concierge.provider', 'openai');
        $providerConfig = config('ai.providers.' . $provider, []);

        if ($provider === 'ollama') {
            return filled($providerConfig['url'] ?? null);
        }

        return filled($providerConfig['key'] ?? null);
    }
}
