<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $statusCounts = Event::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return response()->json([
            'metrics' => [
                'events_total' => (int) $statusCounts->sum(),
                'events_draft' => (int) ($statusCounts[Event::STATUS_DRAFT] ?? 0),
                'events_pending_review' => (int) ($statusCounts[Event::STATUS_PENDING_REVIEW] ?? 0),
                'events_published' => (int) ($statusCounts[Event::STATUS_PUBLISHED] ?? 0),
                'events_cancelled' => (int) ($statusCounts[Event::STATUS_CANCELLED] ?? 0),
                'events_archived' => (int) ($statusCounts[Event::STATUS_ARCHIVED] ?? 0),
                'sessions_total' => (int) EventSession::query()->count(),
                'sessions_future' => (int) EventSession::query()
                    ->where('start_time', '>=', now())
                    ->count(),
            ],
        ]);
    }
}
