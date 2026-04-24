<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizerEventController extends Controller
{
    public function myEvents(Request $request): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'status' => ['nullable', 'in:draft,published,cancelled,archived'],
        ]);

        $events = Event::query()
            ->where('organizer_id', $organizer['id'])
            ->with([
                'category:id,name,slug',
                'ageRating:id,label,min_age',
                'organizer:id,auth_user_id,full_name,email',
            ])
            ->when(
                $validated['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status)
            )
            ->latest('created_at')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString()
            ->through(fn (Event $event) => $this->transformEvent($event));

        return response()->json($events);
    }

    public function store(Request $request): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'poster_url' => ['nullable', 'url', 'max:2048'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'age_rating_id' => ['required', 'integer', 'exists:age_ratings,id'],
            'status' => ['nullable', 'in:draft,published'],
        ]);

        $event = Event::query()->create([
            ...$validated,
            'organizer_id' => $organizer['id'],
            'status' => $validated['status'] ?? Event::STATUS_DRAFT,
        ]);

        $event->load([
            'category:id,name,slug',
            'ageRating:id,label,min_age',
            'organizer:id,auth_user_id,full_name,email',
        ]);

        return response()->json([
            'message' => 'Мероприятие успешно создано.',
            'event' => $this->transformEvent($event),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'poster_url' => ['nullable', 'url', 'max:2048'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'age_rating_id' => ['required', 'integer', 'exists:age_ratings,id'],
            'status' => ['nullable', 'in:draft,published'],
        ]);

        $event = $this->findOrganizerEventOrFail($id, $organizer['id']);

        $event->update([
            ...$validated,
            'status' => $validated['status'] ?? $event->status,
        ]);

        $event = $event->fresh([
            'category:id,name,slug',
            'ageRating:id,label,min_age',
            'organizer:id,auth_user_id,full_name,email',
        ]);

        return response()->json([
            'message' => 'Мероприятие успешно обновлено.',
            'event' => $this->transformEvent($event),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');

        $validated = $request->validate([
            'status' => ['required', 'in:cancelled,archived'],
        ]);

        $event = $this->findOrganizerEventOrFail($id, $organizer['id']);

        $event->update([
            'status' => $validated['status'],
        ]);

        $event->load([
            'category:id,name,slug',
            'ageRating:id,label,min_age',
            'organizer:id,auth_user_id,full_name,email',
        ]);

        return response()->json([
            'message' => 'Статус мероприятия успешно изменен.',
            'event' => $this->transformEvent($event),
        ]);
    }

    private function findOrganizerEventOrFail(int $eventId, int $organizerId): Event
    {
        return Event::query()
            ->whereKey($eventId)
            ->where('organizer_id', $organizerId)
            ->firstOrFail();
    }

    private function transformEvent(Event $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
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
            'organizer_id' => $event->organizer_id,
            'organizer' => [
                'id' => $event->organizer?->auth_user_id,
                'full_name' => $event->organizer?->full_name,
                'email' => $event->organizer?->email,
            ],
            'status' => $event->status,
            'created_at' => $event->created_at?->toISOString(),
            'updated_at' => $event->updated_at?->toISOString(),
        ];
    }
}
