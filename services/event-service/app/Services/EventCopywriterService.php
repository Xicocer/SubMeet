<?php

namespace App\Services;

use App\Ai\Agents\EventCopywriterTextAgent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class EventCopywriterService
{
    /**
     * @param  array<int, string>  $tags
     * @return array{description: string, tips: array<int, string>, mode: string}
     */
    public function rewrite(
        string $title,
        ?string $description = null,
        ?string $categoryName = null,
        ?string $ageRatingLabel = null,
        array $tags = [],
    ): array {
        if (! $this->isConfigured()) {
            return $this->fallback($title, $description, $categoryName, $ageRatingLabel, $tags);
        }

        try {
            $response = (new EventCopywriterTextAgent)->prompt(
                $this->buildPrompt($title, $description, $categoryName, $ageRatingLabel, $tags),
                provider: config('services.concierge.provider'),
                model: config('services.concierge.model') ?: null,
                timeout: (int) config('services.concierge.timeout', 30),
            );

            $payload = $this->parseAiPayload($response->text);

            if ($payload === null) {
                Log::warning('event_copywriter_invalid_ai_payload', [
                    'provider' => config('services.concierge.provider'),
                    'model' => config('services.concierge.model'),
                    'preview' => Str::limit($response->text, 500),
                ]);

                return $this->fallback($title, $description, $categoryName, $ageRatingLabel, $tags, aiFailed: true);
            }

            $rewrittenDescription = Str::squish((string) Arr::get($payload, 'description', ''));
            $tips = collect(Arr::get($payload, 'tips', []))
                ->map(fn (mixed $tip): string => Str::squish((string) $tip))
                ->filter()
                ->take(3)
                ->values()
                ->all();

            if ($rewrittenDescription === '') {
                return $this->fallback($title, $description, $categoryName, $ageRatingLabel, $tags, aiFailed: true);
            }

            return [
                'description' => Str::limit($rewrittenDescription, 1200, ''),
                'tips' => $tips !== [] ? $tips : ['Описание стало понятнее для карточки события.'],
                'mode' => 'ai',
            ];
        } catch (Throwable $exception) {
            Log::warning('event_copywriter_ai_failed', [
                'provider' => config('services.concierge.provider'),
                'model' => config('services.concierge.model'),
                'message' => $exception->getMessage(),
            ]);

            return $this->fallback($title, $description, $categoryName, $ageRatingLabel, $tags, aiFailed: true);
        }
    }

    /**
     * @return array{description?: string, tips?: array<int, string>}|null
     */
    private function parseAiPayload(string $text): ?array
    {
        $cleanText = trim($text);
        $cleanText = preg_replace('/^```(?:json)?\s*/i', '', $cleanText) ?? $cleanText;
        $cleanText = preg_replace('/\s*```$/', '', $cleanText) ?? $cleanText;

        $decoded = json_decode($cleanText, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $cleanText, $matches) !== 1) {
            return null;
        }

        $decoded = json_decode($matches[0], true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<int, string>  $tags
     * @return array{description: string, tips: array<int, string>, mode: string}
     */
    private function fallback(
        string $title,
        ?string $description,
        ?string $categoryName,
        ?string $ageRatingLabel,
        array $tags,
        bool $aiFailed = false,
    ): array {
        $cleanTitle = Str::squish($title);
        $cleanDescription = Str::squish((string) $description);
        $tagLine = collect($tags)
            ->map(fn (string $tag): string => Str::squish($tag))
            ->filter()
            ->take(4)
            ->implode(', ');

        $parts = [];
        $parts[] = $cleanTitle . ' - мероприятие для тех, кто хочет провести вечер ярко и без случайного выбора.';

        if ($cleanDescription !== '') {
            $parts[] = Str::finish($cleanDescription, '.');
        }

        if ($categoryName !== null || $ageRatingLabel !== null) {
            $meta = [];

            if ($categoryName !== null) {
                $meta[] = 'формат: ' . Str::lower($categoryName);
            }

            if ($ageRatingLabel !== null) {
                $meta[] = 'возрастной рейтинг ' . $ageRatingLabel;
            }

            $parts[] = 'В карточке сразу понятны ' . implode(', ', $meta) . '.';
        }

        if ($tagLine !== '') {
            $parts[] = 'По настроению это: ' . $tagLine . '.';
        }

        $parts[] = 'Добавьте событие в планы, выберите удобный сеанс и переходите к бронированию места на схеме зала.';

        return [
            'description' => Str::limit(Str::squish(implode(' ', $parts)), 900, ''),
            'tips' => [
                $aiFailed
                    ? 'Митя не смог получить корректный ответ от модели, поэтому сработал безопасный локальный режим.'
                    : 'AI-ключ не найден, поэтому описание улучшено локально.',
                'Перед публикацией проверьте факты: дату, площадку, участников и цену.',
            ],
            'mode' => 'fallback',
        ];
    }

    /**
     * @param  array<int, string>  $tags
     */
    private function buildPrompt(
        string $title,
        ?string $description,
        ?string $categoryName,
        ?string $ageRatingLabel,
        array $tags,
    ): string {
        return "Перепиши описание мероприятия для карточки билетной платформы.\n"
            . "Верни строго JSON с ключами description и tips. Не добавляй markdown.\n"
            . "Данные события:\n"
            . json_encode([
                'title' => $title,
                'description' => $description,
                'category' => $categoryName,
                'age_rating' => $ageRatingLabel,
                'tags' => array_values($tags),
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
