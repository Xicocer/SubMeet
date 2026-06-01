<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HallRentalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventHallRentalRequestController extends Controller
{
    public function index(Request $request, int $eventId): JsonResponse
    {
        $validated = $request->validate([
            'statuses' => ['nullable', 'string', 'max:100'],
        ]);

        $allowedStatuses = [
            HallRentalRequest::STATUS_PENDING,
            HallRentalRequest::STATUS_APPROVED,
        ];

        $statuses = collect(explode(',', (string) ($validated['statuses'] ?? 'pending,approved')))
            ->map(fn (string $status) => trim($status))
            ->filter(fn (string $status) => in_array($status, $allowedStatuses, true))
            ->values()
            ->all();

        if ($statuses === []) {
            $statuses = $allowedStatuses;
        }

        $rentalRequests = HallRentalRequest::query()
            ->with('hall')
            ->where('event_id', $eventId)
            ->whereIn('status', $statuses)
            ->where('requested_end', '>', now())
            ->orderBy('requested_start')
            ->get();

        return response()->json([
            'data' => $rentalRequests->map(fn (HallRentalRequest $rentalRequest) => [
                'id' => $rentalRequest->id,
                'hall_id' => $rentalRequest->hall_id,
                'event_id' => $rentalRequest->event_id,
                'status' => $rentalRequest->status,
                'requested_start' => $rentalRequest->requested_start?->toISOString(),
                'requested_end' => $rentalRequest->requested_end?->toISOString(),
                'hall' => $rentalRequest->hall ? [
                    'id' => $rentalRequest->hall->id,
                    'name' => $rentalRequest->hall->name,
                    'address' => $rentalRequest->hall->address,
                    'description' => $rentalRequest->hall->description,
                    'photo_urls' => $rentalRequest->hall->photo_urls ?? [],
                ] : null,
            ])->values(),
        ]);
    }
}
