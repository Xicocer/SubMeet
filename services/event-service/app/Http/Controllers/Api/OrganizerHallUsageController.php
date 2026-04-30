<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizerHallUsageController extends Controller
{
    public function __invoke(Request $request, int $hallId): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');

        $futureSessions = EventSession::query()
            ->with('event:id,title')
            ->where('hall_id', $hallId)
            ->where('status', EventSession::STATUS_SCHEDULED)
            ->where('start_time', '>=', now())
            ->whereHas('event', fn ($query) => $query->where('organizer_id', $organizer['id']))
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'hall_id' => $hallId,
            'future_sessions_count' => $futureSessions->count(),
            'has_future_sessions' => $futureSessions->isNotEmpty(),
            'future_sessions' => $futureSessions
                ->take(5)
                ->map(fn (EventSession $session) => [
                    'id' => $session->id,
                    'event_id' => $session->event_id,
                    'event_title' => $session->event?->title,
                    'start_time' => $session->start_time?->toISOString(),
                    'end_time' => $session->end_time?->toISOString(),
                    'status' => $session->status,
                ])
                ->values()
                ->all(),
        ]);
    }
}
