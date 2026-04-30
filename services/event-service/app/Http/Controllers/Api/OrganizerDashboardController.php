<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizerDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');
        $organizerId = (int) ($organizer['id'] ?? 0);

        $eventStatusCounts = Event::query()
            ->where('organizer_id', $organizerId)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $sessionStatusCounts = EventSession::query()
            ->whereHas('event', fn ($query) => $query->where('organizer_id', $organizerId))
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $upcomingSessionsQuery = EventSession::query()
            ->with('event:id,title')
            ->whereHas('event', fn ($query) => $query->where('organizer_id', $organizerId))
            ->where('status', EventSession::STATUS_SCHEDULED)
            ->where('start_time', '>=', now())
            ->orderBy('start_time');

        $upcomingSessionsCount = (clone $upcomingSessionsQuery)->count();

        $upcomingSessions = $upcomingSessionsQuery
            ->orderBy('start_time')
            ->limit(5)
            ->get()
            ->map(fn (EventSession $session) => [
                'id' => $session->id,
                'event_id' => $session->event_id,
                'event_title' => $session->event?->title,
                'hall_id' => $session->hall_id,
                'status' => $session->status,
                'start_time' => $session->start_time?->toISOString(),
                'end_time' => $session->end_time?->toISOString(),
                'base_price' => $session->base_price,
            ])
            ->values()
            ->all();

        $recentEvents = Event::query()
            ->where('organizer_id', $organizerId)
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'status' => $event->status,
                'created_at' => $event->created_at?->toISOString(),
                'updated_at' => $event->updated_at?->toISOString(),
            ])
            ->values()
            ->all();

        return response()->json([
            'metrics' => [
                'events_total' => (int) $eventStatusCounts->sum(),
                'events_published' => (int) ($eventStatusCounts[Event::STATUS_PUBLISHED] ?? 0),
                'events_draft' => (int) ($eventStatusCounts[Event::STATUS_DRAFT] ?? 0),
                'events_cancelled' => (int) ($eventStatusCounts[Event::STATUS_CANCELLED] ?? 0),
                'events_archived' => (int) ($eventStatusCounts[Event::STATUS_ARCHIVED] ?? 0),
                'sessions_total' => (int) $sessionStatusCounts->sum(),
                'sessions_upcoming' => $upcomingSessionsCount,
                'sessions_scheduled' => (int) ($sessionStatusCounts[EventSession::STATUS_SCHEDULED] ?? 0),
                'sessions_cancelled' => (int) ($sessionStatusCounts[EventSession::STATUS_CANCELLED] ?? 0),
                'sessions_completed' => (int) ($sessionStatusCounts[EventSession::STATUS_COMPLETED] ?? 0),
            ],
            'upcoming_sessions' => $upcomingSessions,
            'recent_events' => $recentEvents,
        ]);
    }
}
