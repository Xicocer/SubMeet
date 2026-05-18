<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventSession;
use Illuminate\Http\JsonResponse;

class InternalHallUsageController extends Controller
{
    public function __invoke(int $hallId): JsonResponse
    {
        $futureSessions = EventSession::query()
            ->with('event:id,title,organizer_id')
            ->where('hall_id', $hallId)
            ->where('status', EventSession::STATUS_SCHEDULED)
            ->where('start_time', '>=', now())
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'hall_id' => $hallId,
            'future_sessions_count' => $futureSessions->count(),
            'has_future_sessions' => $futureSessions->isNotEmpty(),
            'future_sessions' => $futureSessions
                ->take(10)
                ->map(fn (EventSession $session) => [
                    'id' => $session->id,
                    'event_id' => $session->event_id,
                    'event_title' => $session->event?->title,
                    'organizer_id' => $session->event?->organizer_id,
                    'start_time' => $session->start_time?->toISOString(),
                    'end_time' => $session->end_time?->toISOString(),
                    'status' => $session->status,
                ])
                ->values()
                ->all(),
        ]);
    }
}
