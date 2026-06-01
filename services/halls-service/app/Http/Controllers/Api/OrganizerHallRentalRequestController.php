<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hall;
use App\Models\HallRentalRequest;
use App\Models\HallUnavailablePeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizerHallRentalRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');

        $validated = $request->validate([
            'event_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:pending,approved,rejected,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $requests = HallRentalRequest::query()
            ->with('hall')
            ->where('organizer_id', $organizer['id'])
            ->when(
                $validated['event_id'] ?? null,
                fn (Builder $query, int $eventId) => $query->where('event_id', $eventId)
            )
            ->when(
                $validated['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status)
            )
            ->latest('created_at')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString()
            ->through(fn (HallRentalRequest $rentalRequest) => $this->transformRequest($rentalRequest));

        return response()->json($requests);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');

        $rentalRequest = HallRentalRequest::query()
            ->with('hall')
            ->whereKey($id)
            ->where('organizer_id', $organizer['id'])
            ->firstOrFail();

        return response()->json($this->transformRequest($rentalRequest));
    }

    public function store(Request $request): JsonResponse
    {
        $organizer = $request->attributes->get('auth_user');

        $validated = $request->validate([
            'hall_id' => ['required', 'integer', 'min:1'],
            'event_id' => ['required', 'integer', 'min:1'],
            'requested_start' => ['nullable', 'date', 'after:now'],
            'requested_end' => ['nullable', 'date', 'after:requested_start'],
            'requested_slots' => ['nullable', 'array', 'min:1', 'max:14'],
            'requested_slots.*.requested_start' => ['required_with:requested_slots', 'date', 'after:now'],
            'requested_slots.*.requested_end' => ['required_with:requested_slots', 'date'],
            'organizer_message' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var Hall $hall */
        $hall = Hall::query()
            ->active()
            ->whereKey($validated['hall_id'])
            ->firstOrFail();

        $slots = $this->normalizeRequestedSlots($validated);

        if ($slots === []) {
            throw ValidationException::withMessages([
                'requested_slots' => ['Выберите хотя бы одну дату для заявки на площадку.'],
            ]);
        }

        $rentalRequests = DB::transaction(function () use ($slots, $hall, $organizer, $validated) {
            return collect($slots)->map(function (array $slot) use ($hall, $organizer, $validated): HallRentalRequest {
                $requestedStart = $slot['requested_start'];
                $requestedEnd = $slot['requested_end'];

                $this->ensureNoApprovedOverlap($hall->id, $requestedStart->toDateTimeString(), $requestedEnd->toDateTimeString());
                $this->ensureNoUnavailableOverlap($hall->id, $requestedStart->toDateTimeString(), $requestedEnd->toDateTimeString());

                $durationMinutes = max(1.0, (float) $requestedStart->diffInMinutes($requestedEnd, true));
                $totalAmount = round(((float) $hall->hourly_rate) * ($durationMinutes / 60), 2);

                return HallRentalRequest::query()->create([
                    'hall_id' => $hall->id,
                    'organizer_id' => $organizer['id'],
                    'event_id' => (int) $validated['event_id'],
                    'requested_start' => $requestedStart,
                    'requested_end' => $requestedEnd,
                    'hourly_rate' => $hall->hourly_rate,
                    'total_amount' => $totalAmount,
                    'status' => HallRentalRequest::STATUS_PENDING,
                    'organizer_message' => $validated['organizer_message'] ?? null,
                ]);
            });
        });

        $rentalRequests->each->load('hall');
        $firstRentalRequest = $rentalRequests->first();
        $createdCount = $rentalRequests->count();

        return response()->json([
            'message' => $createdCount === 1
                ? 'Заявка на аренду площадки отправлена владельцу.'
                : "Заявки на {$createdCount} выбранных дней отправлены владельцу площадки.",
            'rental_request' => $firstRentalRequest ? $this->transformRequest($firstRentalRequest) : null,
            'rental_requests' => $rentalRequests
                ->map(fn (HallRentalRequest $rentalRequest) => $this->transformRequest($rentalRequest))
                ->values(),
        ], 201);
    }

    private function normalizeRequestedSlots(array $validated): array
    {
        $rawSlots = $validated['requested_slots'] ?? null;

        if (is_array($rawSlots) && $rawSlots !== []) {
            return collect($rawSlots)
                ->map(function (array $slot): array {
                    $requestedStart = CarbonImmutable::parse($slot['requested_start']);
                    $requestedEnd = CarbonImmutable::parse($slot['requested_end']);

                    if ($requestedEnd->lessThanOrEqualTo($requestedStart)) {
                        throw ValidationException::withMessages([
                            'requested_slots' => ['Окончание аренды должно быть позже начала для каждой выбранной даты.'],
                        ]);
                    }

                    return [
                        'requested_start' => $requestedStart,
                        'requested_end' => $requestedEnd,
                    ];
                })
                ->sortBy(fn (array $slot) => $slot['requested_start']->timestamp)
                ->values()
                ->all();
        }

        if (! isset($validated['requested_start'], $validated['requested_end'])) {
            return [];
        }

        $requestedStart = CarbonImmutable::parse($validated['requested_start']);
        $requestedEnd = CarbonImmutable::parse($validated['requested_end']);

        if ($requestedEnd->lessThanOrEqualTo($requestedStart)) {
            throw ValidationException::withMessages([
                'requested_end' => ['Окончание аренды должно быть позже начала.'],
            ]);
        }

        return [
            [
                'requested_start' => $requestedStart,
                'requested_end' => $requestedEnd,
            ],
        ];
    }

    private function ensureNoApprovedOverlap(int $hallId, string $requestedStart, string $requestedEnd): void
    {
        $hasOverlap = HallRentalRequest::query()
            ->approved()
            ->where('hall_id', $hallId)
            ->where('requested_start', '<', $requestedEnd)
            ->where('requested_end', '>', $requestedStart)
            ->exists();

        if ($hasOverlap) {
            throw ValidationException::withMessages([
                'hall_id' => ['На это время площадка уже подтверждена для другого мероприятия.'],
            ]);
        }
    }

    private function ensureNoUnavailableOverlap(int $hallId, string $requestedStart, string $requestedEnd): void
    {
        $hasOverlap = HallUnavailablePeriod::query()
            ->where('hall_id', $hallId)
            ->where('unavailable_start', '<', $requestedEnd)
            ->where('unavailable_end', '>', $requestedStart)
            ->exists();

        if ($hasOverlap) {
            throw ValidationException::withMessages([
                'hall_id' => ['На это время площадка недоступна по внутреннему графику владельца.'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function transformRequest(HallRentalRequest $rentalRequest): array
    {
        return [
            'id' => $rentalRequest->id,
            'hall_id' => $rentalRequest->hall_id,
            'event_id' => $rentalRequest->event_id,
            'organizer_id' => $rentalRequest->organizer_id,
            'status' => $rentalRequest->status,
            'requested_start' => $rentalRequest->requested_start?->toISOString(),
            'requested_end' => $rentalRequest->requested_end?->toISOString(),
            'hourly_rate' => (float) $rentalRequest->hourly_rate,
            'total_amount' => $rentalRequest->calculatedTotalAmount(),
            'duration_minutes' => $rentalRequest->durationMinutes(),
            'organizer_message' => $rentalRequest->organizer_message,
            'response_note' => $rentalRequest->response_note,
            'responded_at' => $rentalRequest->responded_at?->toISOString(),
            'created_at' => $rentalRequest->created_at?->toISOString(),
            'updated_at' => $rentalRequest->updated_at?->toISOString(),
            'hall' => $rentalRequest->hall ? [
                'id' => $rentalRequest->hall->id,
                'name' => $rentalRequest->hall->name,
                'address' => $rentalRequest->hall->address,
                'description' => $rentalRequest->hall->description,
                'photo_urls' => $rentalRequest->hall->photo_urls ?? [],
                'hourly_rate' => (float) $rentalRequest->hall->hourly_rate,
                'status' => $rentalRequest->hall->status,
                'capacities' => [
                    'seat' => $rentalRequest->hall->seat_capacity,
                    'vip' => $rentalRequest->hall->vip_capacity,
                    'dancefloor' => $rentalRequest->hall->dancefloor_capacity,
                    'total' => $rentalRequest->hall->total_capacity,
                ],
            ] : null,
        ];
    }
}
