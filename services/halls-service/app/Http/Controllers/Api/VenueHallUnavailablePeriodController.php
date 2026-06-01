<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hall;
use App\Models\HallUnavailablePeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VenueHallUnavailablePeriodController extends Controller
{
    public function index(Request $request, int $hallId): JsonResponse
    {
        $venueOwner = $request->attributes->get('auth_user');

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $hall = $this->resolveVenueHall($hallId, (int) $venueOwner['id']);

        $periods = HallUnavailablePeriod::query()
            ->where('hall_id', $hall->id)
            ->when($validated['from'] ?? null, fn (Builder $query, string $from) => $query->where('unavailable_end', '>=', CarbonImmutable::parse($from)->startOfDay()))
            ->when($validated['to'] ?? null, fn (Builder $query, string $to) => $query->where('unavailable_start', '<=', CarbonImmutable::parse($to)->endOfDay()))
            ->orderBy('unavailable_start')
            ->get();

        return response()->json([
            'data' => $periods->map(fn (HallUnavailablePeriod $period) => $this->transformPeriod($period))->values(),
        ]);
    }

    public function store(Request $request, int $hallId): JsonResponse
    {
        $venueOwner = $request->attributes->get('auth_user');

        $validated = $request->validate([
            'unavailable_start' => ['required', 'date', 'after:now'],
            'unavailable_end' => ['required', 'date', 'after:unavailable_start'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $hall = $this->resolveVenueHall($hallId, (int) $venueOwner['id']);

        $unavailableStart = CarbonImmutable::parse($validated['unavailable_start']);
        $unavailableEnd = CarbonImmutable::parse($validated['unavailable_end']);

        $hasApprovedOverlap = $hall->rentalRequests()
            ->approved()
            ->where('requested_start', '<', $unavailableEnd)
            ->where('requested_end', '>', $unavailableStart)
            ->exists();

        if ($hasApprovedOverlap) {
            throw ValidationException::withMessages([
                'unavailable_start' => ['На этот интервал уже есть подтвержденная аренда площадки.'],
            ]);
        }

        $period = HallUnavailablePeriod::query()->create([
            'hall_id' => $hall->id,
            'unavailable_start' => $unavailableStart,
            'unavailable_end' => $unavailableEnd,
            'reason' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'message' => 'Период недоступности площадки сохранен.',
            'period' => $this->transformPeriod($period),
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $venueOwner = $request->attributes->get('auth_user');

        $period = HallUnavailablePeriod::query()
            ->whereKey($id)
            ->whereHas('hall', fn (Builder $query) => $query->where('venue_owner_id', $venueOwner['id']))
            ->firstOrFail();

        $period->delete();

        return response()->json([
            'message' => 'Период недоступности площадки удален.',
        ]);
    }

    private function resolveVenueHall(int $hallId, int $venueOwnerId): Hall
    {
        return Hall::query()
            ->whereKey($hallId)
            ->where('venue_owner_id', $venueOwnerId)
            ->firstOrFail();
    }

    private function transformPeriod(HallUnavailablePeriod $period): array
    {
        return [
            'id' => $period->id,
            'hall_id' => $period->hall_id,
            'unavailable_start' => $period->unavailable_start?->toISOString(),
            'unavailable_end' => $period->unavailable_end?->toISOString(),
            'reason' => $period->reason,
            'created_at' => $period->created_at?->toISOString(),
            'updated_at' => $period->updated_at?->toISOString(),
        ];
    }
}
