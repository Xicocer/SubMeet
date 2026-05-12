<?php

namespace App\Services;

use App\Ai\AuthConversationParticipant;
use App\Ai\Agents\EventChatConciergeAgent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Streaming\Events\TextDelta;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EventAssistantChatService
{
    public function __construct(
        private readonly AuthServiceClient $authServiceClient,
        private readonly EventAssistantCandidateResolver $candidateResolver,
        private readonly EventDiscoveryQueryAnalyzer $queryAnalyzer,
        private readonly EventConciergeNarrator $fallbackNarrator,
    ) {
    }

    public function stream(string $message, ?string $token = null, ?string $conversationId = null): StreamedResponse
    {
        $analysis = $this->queryAnalyzer->analyze($message);
        $participant = $this->resolveOptionalParticipant($token);

        $candidatePayload = $this->candidateResolver->resolve(
            analysis: $analysis,
            userId: $participant?->id,
            token: $token,
        );

        $cards = collect($candidatePayload['items'])
            ->take(4)
            ->values()
            ->all();

        $metaPayload = [
            'personalized' => $participant !== null,
            'candidate_source' => $candidatePayload['candidate_source'],
            'context' => [
                'intent_label' => $analysis['intent_label'],
                'timeframe_label' => $analysis['timeframe_label'],
            ],
            'conversation_id' => $conversationId,
            'history_enabled' => $participant !== null,
        ];

        if (! $this->isConfigured()) {
            $fallback = $this->fallbackNarrator->narrate($message, $analysis, $candidatePayload['items']);
            $fallbackConversationId = null;
            $savedHistory = false;

            if ($participant !== null) {
                $fallbackConversationId = $this->persistFallbackConversation(
                    conversationId: $conversationId,
                    participant: $participant,
                    userMessage: $message,
                    assistantMessage: $fallback['answer'],
                    cards: $cards,
                );
                $savedHistory = $fallbackConversationId !== null;
            }

            return $this->streamFallback(
                answer: $fallback['answer'],
                cards: $cards,
                metaPayload: $metaPayload,
                conversationId: $fallbackConversationId,
                savedHistory: $savedHistory,
            );
        }

        $resolvedConversationId = $participant !== null
            ? $this->resolveConversationIdForParticipant($conversationId, $participant)
            : null;

        $agent = (new EventChatConciergeAgent())->withRequestContext(
            $this->buildAgentContextSummary(
                message: $message,
                analysis: $analysis,
                candidates: $candidatePayload['items'],
                history: $participant !== null
                    ? $this->buildConversationHistoryContext($resolvedConversationId, $participant->id)
                    : [],
            )
        );

        $stream = $agent->stream(
            $message,
            provider: config('services.concierge.provider'),
            model: config('services.concierge.model') ?: null,
            timeout: (int) config('services.concierge.timeout', 30),
        );

        $finalConversationId = $resolvedConversationId;
        $savedHistory = false;

        $stream->then(function ($response) use (&$finalConversationId, &$savedHistory, $cards, $message, $participant, $resolvedConversationId): void {
            if ($participant === null) {
                return;
            }

            $finalConversationId = $this->persistAiConversation(
                conversationId: $resolvedConversationId,
                participant: $participant,
                userMessage: $message,
                assistantMessage: $response->text,
                cards: $cards,
            );
            $savedHistory = $finalConversationId !== null;

            if ($finalConversationId !== null && $cards !== []) {
                $this->persistRecommendationsForLatestAssistantMessage(
                    conversationId: $finalConversationId,
                    cards: $cards,
                );
            }
        });

        return response()->stream(function () use ($stream, $cards, $metaPayload, &$finalConversationId, &$savedHistory): void {
            $this->emitSse('meta', $metaPayload);

            try {
                foreach ($stream as $event) {
                    if ($event instanceof TextDelta) {
                        $this->emitSse('text-delta', [
                            'delta' => $event->delta,
                        ]);
                    }
                }
            } catch (Throwable $exception) {
                Log::warning('event_assistant_stream_failed', [
                    'provider' => config('services.concierge.provider'),
                    'model' => config('services.concierge.model'),
                    'conversation_id' => $finalConversationId,
                    'message' => $exception->getMessage(),
                ]);

                $this->emitSse('error', [
                    'message' => 'Не удалось получить поток ответа от AI-консьержа.',
                    'detail' => app()->hasDebugModeEnabled() ? $exception->getMessage() : null,
                ]);

                return;
            }

            $this->emitSse('recommendations', [
                'items' => $cards,
            ]);

            $this->emitSse('done', [
                'conversation_id' => $finalConversationId,
                'saved_history' => $savedHistory,
            ]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function conversations(int $userId): array
    {
        $this->normalizeConversationTitles($userId);

        return DB::table('agent_conversations')
            ->where('user_id', $userId)
            ->whereIn('id', function ($query): void {
                $query->select('conversation_id')
                    ->from('agent_conversation_messages')
                    ->where('agent', EventChatConciergeAgent::class);
            })
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get([
                'id',
                'title',
                'created_at',
                'updated_at',
            ])
            ->map(fn ($conversation) => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'created_at' => $conversation->created_at,
                'updated_at' => $conversation->updated_at,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function conversation(int $userId, string $conversationId): ?array
    {
        $this->normalizeConversationUserMessages($conversationId, $userId);

        $conversation = DB::table('agent_conversations')
            ->where('id', $conversationId)
            ->where('user_id', $userId)
            ->first([
                'id',
                'title',
                'created_at',
                'updated_at',
            ]);

        if ($conversation === null) {
            return null;
        }

        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('agent', EventChatConciergeAgent::class)
            ->orderBy('created_at')
            ->get([
                'id',
                'role',
                'content',
                'meta',
                'created_at',
            ])
            ->map(function ($message): array {
                $meta = json_decode((string) $message->meta, true);

                return [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'created_at' => $message->created_at,
                    'recommendations' => is_array($meta['recommendations'] ?? null)
                        ? array_values($meta['recommendations'])
                        : [],
                ];
            })
            ->all();

        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'created_at' => $conversation->created_at,
            'updated_at' => $conversation->updated_at,
            'messages' => $messages,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveOptionalAuthUser(?string $token): ?array
    {
        if (! $token) {
            return null;
        }

        try {
            $user = $this->authServiceClient->getCurrentUser($token);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($user) || (int) ($user['status'] ?? 0) !== 1) {
            return null;
        }

        return $user;
    }

    private function streamFallback(
        string $answer,
        array $cards,
        array $metaPayload,
        ?string $conversationId = null,
        bool $savedHistory = false,
    ): StreamedResponse
    {
        $chunks = preg_split('/(\s+)/u', $answer, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

        return response()->stream(function () use ($chunks, $cards, $metaPayload, $conversationId, $savedHistory): void {
            $this->emitSse('meta', $metaPayload);

            foreach ($chunks as $chunk) {
                $this->emitSse('text-delta', [
                    'delta' => $chunk,
                ]);

                usleep(18000);
            }

            $this->emitSse('recommendations', [
                'items' => $cards,
            ]);

            $this->emitSse('done', [
                'conversation_id' => $conversationId,
                'saved_history' => $savedHistory,
            ]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @param  array<int, array<string, mixed>>  $candidates
     */
    private function buildAgentContextSummary(string $message, array $analysis, array $candidates, array $history = []): array
    {
        $candidateSlice = array_slice($candidates, 0, 8);

        $summary = [
            'current_user_request' => $message,
            'intent_label' => $analysis['intent_label'] ?? null,
            'timeframe_label' => $analysis['timeframe_label'] ?? null,
            'preferred_categories' => $analysis['preferred_categories'] ?? [],
            'preferred_tags' => $analysis['preferred_tags'] ?? [],
            'available_candidates' => array_map(function (array $candidate): array {
                return [
                    'id' => (int) ($candidate['id'] ?? 0),
                    'title' => (string) ($candidate['title'] ?? ''),
                    'category_name' => $candidate['category_name'] ?? $candidate['category'] ?? null,
                    'event_date' => $candidate['event_date'] ?? null,
                    'price' => $candidate['price'] ?? null,
                    'age_rating' => $candidate['age_rating'] ?? null,
                    'city' => $candidate['city'] ?? null,
                    'venue_address' => $candidate['venue_address'] ?? null,
                    'hall_name' => $candidate['hall_name'] ?? null,
                    'tags' => $candidate['tags'] ?? '',
                    'description' => Str::limit((string) ($candidate['description'] ?? ''), 240),
                ];
            }, $candidateSlice),
        ];

        if ($history !== []) {
            $summary['recent_dialogue'] = $history;
        }

        return $summary;

        return "Ответь пользователю по его запросу и используй только доступные кандидаты.\n"
            . "Если рекомендуешь несколько событий, кратко опиши, почему каждое подходит.\n"
            . "Не придумывай событий вне списка.\n"
            . "Контекст:\n"
            . json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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

    private function emitSse(string $event, array $payload): void
    {
        echo 'event: ' . $event . "\n";
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";

        if (function_exists('ob_flush')) {
            @ob_flush();
        }

        flush();
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function buildConversationHistoryContext(?string $conversationId, int $userId): array
    {
        if (! $conversationId) {
            return [];
        }

        $this->normalizeConversationUserMessages($conversationId, $userId);

        return DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->where('agent', EventChatConciergeAgent::class)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['role', 'content'])
            ->reverse()
            ->map(fn ($message) => [
                'role' => (string) $message->role,
                'content' => Str::limit(trim((string) $message->content), 400),
            ])
            ->values()
            ->all();
    }

    private function persistAiConversation(
        ?string $conversationId,
        AuthConversationParticipant $participant,
        string $userMessage,
        string $assistantMessage,
        array $cards,
    ): ?string {
        return $this->persistFallbackConversation(
            conversationId: $conversationId,
            participant: $participant,
            userMessage: $userMessage,
            assistantMessage: $assistantMessage,
            cards: $cards,
        );
    }

    private function persistLatestUserMessageContent(string $conversationId, string $content): void
    {
        $message = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('agent', EventChatConciergeAgent::class)
            ->where('role', 'user')
            ->orderByDesc('created_at')
            ->first(['id', 'content']);

        if ($message === null || (string) $message->content === $content) {
            return;
        }

        DB::table('agent_conversation_messages')
            ->where('id', $message->id)
            ->update([
                'content' => $content,
                'updated_at' => now(),
            ]);
    }

    private function normalizeConversationUserMessages(string $conversationId, int $userId): void
    {
        $messages = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->where('agent', EventChatConciergeAgent::class)
            ->where('role', 'user')
            ->orderBy('created_at')
            ->get(['id', 'content']);

        foreach ($messages as $message) {
            $normalized = $this->extractLegacyPromptUserMessage((string) $message->content);

            if ($normalized === null || $normalized === (string) $message->content) {
                continue;
            }

            DB::table('agent_conversation_messages')
                ->where('id', $message->id)
                ->update([
                    'content' => $normalized,
                    'updated_at' => now(),
                ]);
        }

        $this->normalizeConversationTitles($userId, $conversationId);
    }

    private function normalizeConversationTitles(int $userId, ?string $conversationId = null): void
    {
        $query = DB::table('agent_conversations')
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->limit($conversationId ? 1 : 20);

        if ($conversationId !== null) {
            $query->where('id', $conversationId);
        }

        $conversations = $query->get(['id', 'title']);

        foreach ($conversations as $conversation) {
            if (! $this->isLegacyConversationContent((string) $conversation->title)) {
                continue;
            }

            $firstUserMessage = DB::table('agent_conversation_messages')
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $userId)
                ->where('agent', EventChatConciergeAgent::class)
                ->where('role', 'user')
                ->orderBy('created_at')
                ->first(['content']);

            if ($firstUserMessage === null) {
                continue;
            }

            $normalizedContent = $this->extractLegacyPromptUserMessage((string) $firstUserMessage->content)
                ?? trim((string) $firstUserMessage->content);

            if ($normalizedContent === '') {
                continue;
            }

            $normalizedTitle = Str::limit($normalizedContent, 100, preserveWords: true);

            if ($normalizedTitle === (string) $conversation->title) {
                continue;
            }

            DB::table('agent_conversations')
                ->where('id', $conversation->id)
                ->update([
                    'title' => $normalizedTitle,
                    'updated_at' => now(),
                ]);
        }
    }

    private function extractLegacyPromptUserMessage(string $content): ?string
    {
        if (! $this->isLegacyConversationContent($content)) {
            return null;
        }

        if (preg_match('/"current_user_request"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u', $content, $matches) !== 1) {
            return null;
        }

        $decoded = json_decode('"' . $matches[1] . '"');

        if (! is_string($decoded)) {
            return null;
        }

        $decoded = trim($decoded);

        return $decoded !== '' ? $decoded : null;
    }

    private function isLegacyConversationContent(string $content): bool
    {
        $normalized = trim($content);

        return str_contains($normalized, 'current_user_request')
            || str_contains($normalized, 'available_candidates')
            || str_contains($normalized, 'Контекст:')
            || str_starts_with($normalized, 'Ответь пользователю');
    }

    private function persistRecommendationsForLatestAssistantMessage(string $conversationId, array $cards): void
    {
        $message = DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->where('agent', EventChatConciergeAgent::class)
            ->where('role', 'assistant')
            ->orderByDesc('created_at')
            ->first(['id', 'meta']);

        if ($message === null) {
            return;
        }

        $meta = json_decode((string) $message->meta, true);

        if (! is_array($meta)) {
            $meta = [];
        }

        $meta['recommendations'] = array_values($cards);

        DB::table('agent_conversation_messages')
            ->where('id', $message->id)
            ->update([
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    private function resolveOptionalParticipant(?string $token): ?AuthConversationParticipant
    {
        $user = $this->resolveOptionalAuthUser($token);

        if ($user === null) {
            return null;
        }

        return new AuthConversationParticipant(
            id: (int) $user['id'],
            displayName: Arr::get($user, 'organizer_profile.company_name')
                ?: ($user['full_name'] ?? null),
        );
    }

    private function persistFallbackConversation(
        ?string $conversationId,
        AuthConversationParticipant $participant,
        string $userMessage,
        string $assistantMessage,
        array $cards,
    ): ?string {
        $resolvedConversationId = $this->resolveConversationIdForParticipant($conversationId, $participant);
        $timestamp = now();

        if ($resolvedConversationId === null) {
            $resolvedConversationId = (string) Str::uuid7();

            DB::table('agent_conversations')->insert([
                'id' => $resolvedConversationId,
                'user_id' => $participant->id,
                'title' => Str::limit($userMessage, 100, preserveWords: true),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        } else {
            DB::table('agent_conversations')
                ->where('id', $resolvedConversationId)
                ->where('user_id', $participant->id)
                ->update([
                    'updated_at' => $timestamp,
                ]);
        }

        DB::table('agent_conversation_messages')->insert([
            [
                'id' => (string) Str::uuid7(),
                'conversation_id' => $resolvedConversationId,
                'user_id' => $participant->id,
                'agent' => EventChatConciergeAgent::class,
                'role' => 'user',
                'content' => $userMessage,
                'attachments' => '[]',
                'tool_calls' => '[]',
                'tool_results' => '[]',
                'usage' => '[]',
                'meta' => '[]',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'id' => (string) Str::uuid7(),
                'conversation_id' => $resolvedConversationId,
                'user_id' => $participant->id,
                'agent' => EventChatConciergeAgent::class,
                'role' => 'assistant',
                'content' => $assistantMessage,
                'attachments' => '[]',
                'tool_calls' => '[]',
                'tool_results' => '[]',
                'usage' => '[]',
                'meta' => json_encode([
                    'recommendations' => array_values($cards),
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);

        return $resolvedConversationId;
    }

    private function resolveConversationIdForParticipant(?string $conversationId, AuthConversationParticipant $participant): ?string
    {
        if (! $conversationId) {
            return null;
        }

        return DB::table('agent_conversations')
            ->where('id', $conversationId)
            ->where('user_id', $participant->id)
            ->value('id');
    }
}
