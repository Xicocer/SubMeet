<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizerDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $venueOwner = $request->attributes->get('auth_user');
        $venueOwnerId = (int) ($venueOwner['id'] ?? 0);

        $statusCounts = Hall::query()
            ->where('venue_owner_id', $venueOwnerId)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $capacitySummary = Hall::query()
            ->where('venue_owner_id', $venueOwnerId)
            ->selectRaw('
                COALESCE(SUM(total_capacity), 0) as total_capacity,
                COALESCE(MAX(total_capacity), 0) as largest_capacity,
                COALESCE(ROUND(AVG(total_capacity)), 0) as average_capacity,
                COALESCE(ROUND(AVG(hourly_rate), 2), 0) as average_hourly_rate
            ')
            ->first();

        $recentHalls = Hall::query()
            ->where('venue_owner_id', $venueOwnerId)
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (Hall $hall) => [
                'id' => $hall->id,
                'name' => $hall->name,
                'address' => $hall->address,
                'status' => $hall->status,
                'total_capacity' => $hall->total_capacity,
                'hourly_rate' => (float) $hall->hourly_rate,
                'updated_at' => $hall->updated_at?->toISOString(),
            ])
            ->values()
            ->all();

        return response()->json([
            'metrics' => [
                'halls_total' => (int) $statusCounts->sum(),
                'halls_active' => (int) ($statusCounts[Hall::STATUS_ACTIVE] ?? 0),
                'halls_draft' => (int) ($statusCounts[Hall::STATUS_DRAFT] ?? 0),
                'halls_archived' => (int) ($statusCounts[Hall::STATUS_ARCHIVED] ?? 0),
                'capacity_total' => (int) ($capacitySummary?->total_capacity ?? 0),
                'capacity_largest' => (int) ($capacitySummary?->largest_capacity ?? 0),
                'capacity_average' => (int) ($capacitySummary?->average_capacity ?? 0),
                'hourly_rate_average' => (float) ($capacitySummary?->average_hourly_rate ?? 0),
            ],
            'recent_halls' => $recentHalls,
        ]);
    }
}
