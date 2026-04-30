<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventSession;
use Illuminate\Http\JsonResponse;

class SessionController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $session = EventSession::query()
            ->available()
            ->whereKey($id)
            ->whereHas('event', fn ($query) => $query->published())
            ->with([
                'event.category:id,name,slug',
                'event.ageRating:id,label,min_age',
            ])
            ->firstOrFail();

        return response()->json([
            'id' => $session->id,
            'event_id' => $session->event_id,
            'hall_id' => $session->hall_id,
            'start_time' => $session->start_time?->toISOString(),
            'end_time' => $session->end_time?->toISOString(),
            'base_price' => $session->base_price,
            'status' => $session->status,
            'event' => [
                'id' => $session->event?->id,
                'title' => $session->event?->title,
                'organizer_id' => $session->event?->organizer_id,
                'category' => [
                    'id' => $session->event?->category?->id,
                    'name' => $session->event?->category?->name,
                    'slug' => $session->event?->category?->slug,
                ],
                'age_rating' => [
                    'id' => $session->event?->ageRating?->id,
                    'label' => $session->event?->ageRating?->label,
                    'min_age' => $session->event?->ageRating?->min_age,
                ],
            ],
        ]);
    }
}
