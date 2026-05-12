<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminEventModerationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:draft,pending_review,published,cancelled,archived'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $events = Event::query()
            ->with([
                'category:id,name,slug',
                'ageRating:id,label,min_age',
                'organizer:id,auth_user_id,full_name,company_name,email',
                'tags:id,name,slug',
            ])
            ->when(
                $validated['status'] ?? null,
                fn ($query, string $status) => $query->where('status', $status)
            )
            ->when(
                $validated['search'] ?? null,
                function ($query, string $search) {
                    $like = '%' . trim($search) . '%';

                    $query->where(function ($builder) use ($like) {
                        $builder
                            ->where('title', 'like', $like)
                            ->orWhere('description', 'like', $like)
                            ->orWhereHas('organizer', function ($organizerQuery) use ($like) {
                                $organizerQuery
                                    ->where('full_name', 'like', $like)
                                    ->orWhere('company_name', 'like', $like)
                                    ->orWhere('email', 'like', $like);
                            })
                            ->orWhereHas('category', function ($categoryQuery) use ($like) {
                                $categoryQuery->where('name', 'like', $like);
                            })
                            ->orWhereHas('tags', function ($tagQuery) use ($like) {
                                $tagQuery->where('name', 'like', $like);
                            });
                    });
                }
            )
            ->latest('updated_at')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString()
            ->through(fn (Event $event) => $this->transformEvent($event));

        return response()->json($events);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approve,needs_revision,unpublish'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $event = Event::query()
            ->with([
                'category:id,name,slug',
                'ageRating:id,label,min_age',
                'organizer:id,auth_user_id,full_name,company_name,email',
                'tags:id,name,slug',
            ])
            ->findOrFail($id);

        $targetStatus = match ($validated['decision']) {
            'approve' => Event::STATUS_PUBLISHED,
            'needs_revision' => Event::STATUS_DRAFT,
            'unpublish' => Event::STATUS_ARCHIVED,
        };

        $event->update([
            'status' => $targetStatus,
            'moderation_note' => $validated['note'] ?? null,
            'moderated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Event moderation decision was applied.',
            'event' => $this->transformEvent($event->fresh([
                'category:id,name,slug',
                'ageRating:id,label,min_age',
                'organizer:id,auth_user_id,full_name,company_name,email',
                'tags:id,name,slug',
            ])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformEvent(Event $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'poster_url' => $event->poster_url,
            'status' => $event->status,
            'moderation_note' => $event->moderation_note,
            'moderated_at' => $event->moderated_at?->toISOString(),
            'created_at' => $event->created_at?->toISOString(),
            'updated_at' => $event->updated_at?->toISOString(),
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
                'company_name' => $event->organizer?->company_name,
                'display_name' => $event->organizer?->company_name ?? $event->organizer?->full_name,
                'email' => $event->organizer?->email,
            ],
            'tags' => $event->tags
                ->map(fn (Tag $tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                ])
                ->values()
                ->all(),
        ];
    }
}
