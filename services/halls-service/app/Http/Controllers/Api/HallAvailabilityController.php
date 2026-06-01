<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hall;
use App\Models\HallRentalRequest;
use App\Models\HallUnavailablePeriod;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HallAvailabilityController extends Controller
{
    public function __invoke(Request $request, int $hallId): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $hallQuery = Hall::query()->whereKey($hallId);
        $venueOwner = $request->attributes->get('auth_user');

        if (is_array($venueOwner) && isset($venueOwner['id'])) {
            $hallQuery->where('venue_owner_id', (int) $venueOwner['id']);
        } else {
            $hallQuery->active();
        }

        /** @var Hall $hall */
        $hall = $hallQuery->firstOrFail();

        $from = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'])->startOfDay()
            : CarbonImmutable::now()->startOfMonth();

        $to = isset($validated['to'])
            ? CarbonImmutable::parse($validated['to'])->endOfDay()
            : $from->endOfMonth();

        if ($from->diffInDays($to) > 62) {
            $to = $from->addDays(62)->endOfDay();
        }

        $approvedRequests = HallRentalRequest::query()
            ->where('hall_id', $hall->id)
            ->where('status', HallRentalRequest::STATUS_APPROVED)
            ->where('requested_start', '<=', $to)
            ->where('requested_end', '>=', $from)
            ->orderBy('requested_start')
            ->get();

        $unavailablePeriods = HallUnavailablePeriod::query()
            ->where('hall_id', $hall->id)
            ->where('unavailable_start', '<=', $to)
            ->where('unavailable_end', '>=', $from)
            ->orderBy('unavailable_start')
            ->get();

        return response()->json([
            'hall_id' => $hall->id,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'days' => $this->buildDays($from, $to, $approvedRequests, $unavailablePeriods),
            'booked_periods' => $approvedRequests->map(fn (HallRentalRequest $rentalRequest) => [
                'id' => $rentalRequest->id,
                'event_id' => $rentalRequest->event_id,
                'start' => $rentalRequest->requested_start?->toISOString(),
                'end' => $rentalRequest->requested_end?->toISOString(),
                'status' => $rentalRequest->status,
            ])->values(),
            'unavailable_periods' => $unavailablePeriods->map(fn (HallUnavailablePeriod $period) => [
                'id' => $period->id,
                'start' => $period->unavailable_start?->toISOString(),
                'end' => $period->unavailable_end?->toISOString(),
                'reason' => $period->reason,
            ])->values(),
        ]);
    }

    private function buildDays($from, $to, $approvedRequests, $unavailablePeriods): array
    {
        $days = [];
        $cursor = $from->startOfDay();
        $lastDay = $to->startOfDay();

        while ($cursor->lessThanOrEqualTo($lastDay)) {
            $dayStart = $cursor->startOfDay();
            $dayEnd = $cursor->endOfDay();

            $bookedCount = $approvedRequests->filter(
                fn (HallRentalRequest $request) => $request->requested_start < $dayEnd
                    && $request->requested_end > $dayStart
            )->count();

            $unavailableCount = $unavailablePeriods->filter(
                fn (HallUnavailablePeriod $period) => $period->unavailable_start < $dayEnd
                    && $period->unavailable_end > $dayStart
            )->count();

            $status = 'free';

            if ($bookedCount > 0) {
                $status = 'booked';
            } elseif ($unavailableCount > 0) {
                $status = 'unavailable';
            }

            $days[] = [
                'date' => $cursor->toDateString(),
                'status' => $status,
                'booked_count' => $bookedCount,
                'unavailable_count' => $unavailableCount,
            ];

            $cursor = $cursor->addDay();
        }

        return $days;
    }
}
