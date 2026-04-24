<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'age' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', 'in:newest,oldest,title_asc,title_desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Event::query()
            ->published()
            ->with([
                'category:id,name,slug',
                'ageRating:id,label,min_age',
                'organizer:id,auth_user_id,full_name,email',
            ]);

        $query->when(
            $validated['search'] ?? null,
            fn ($builder, string $search) => $builder->where('title', 'like', '%' . $search . '%')
        );

        $query->when(
            $validated['category'] ?? null,
            fn ($builder, string $categorySlug) => $builder->whereHas(
                'category',
                fn ($categoryQuery) => $categoryQuery->where('slug', $categorySlug)
            )
        );

        $query->when(
            $validated['age'] ?? null,
            fn ($builder, int $age) => $builder->whereHas(
                'ageRating',
                fn ($ageRatingQuery) => $ageRatingQuery->where('min_age', $age)
            )
        );

        match ($validated['sort'] ?? 'newest') {
            'oldest' => $query->oldest('created_at'),
            'title_asc' => $query->orderBy('title'),
            'title_desc' => $query->orderByDesc('title'),
            default => $query->latest('created_at'),
        };

        $events = $query
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString()
            ->through(fn (Event $event) => $this->transformEvent($event));

        return response()->json($events);
    }

    public function show(int $id): JsonResponse
    {
        $event = Event::query()
            ->published()
            ->with([
                'category:id,name,slug',
                'ageRating:id,label,min_age',
                'organizer:id,auth_user_id,full_name,email',
            ])
            ->findOrFail($id);

        return response()->json($this->transformEvent($event, true));
    }

    public function sessions(int $id): JsonResponse
    {
        $event = Event::query()
            ->published()
            ->findOrFail($id);

        $sessions = EventSession::query()
            ->where('event_id', $event->id)
            ->available()
            ->orderBy('start_time')
            ->get()
            ->map(fn (EventSession $session) => $this->transformSession($session));

        return response()->json($sessions);
    }

    private function transformEvent(Event $event, bool $detailed = false): array
    {
        $payload = [
            'id' => $event->id,
            'title' => $event->title,
            'poster_url' => $event->poster_url,
            'category' => [
                'id' => $event->category?->id,
                'name' => $event->category?->name,
                'slug' => $event->category?->slug,
            ],
            'age_rating' => [
                'id' => $event->ageRating?->id,
                'label' => $event->ageRating?->label,
                'min_age' => $event->ageRating?->min_age,
            ],
            'organizer' => [
                'id' => $event->organizer?->auth_user_id,
                'full_name' => $event->organizer?->full_name,
                'email' => $event->organizer?->email,
            ],
        ];

        if ($detailed) {
            $payload['description'] = $event->description;
            $payload['organizer_id'] = $event->organizer_id;
            $payload['status'] = $event->status;
            $payload['created_at'] = $event->created_at?->toISOString();
            $payload['updated_at'] = $event->updated_at?->toISOString();
        }

        return $payload;
    }

    private function transformSession(EventSession $session): array
    {
        return [
            'id' => $session->id,
            'event_id' => $session->event_id,
            'hall_id' => $session->hall_id,
            'start_time' => $session->start_time?->toISOString(),
            'end_time' => $session->end_time?->toISOString(),
            'base_price' => $session->base_price,
            'status' => $session->status,
        ];
    }
}
