<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HallRentalRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VenueHallRentalRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $venueOwner = $request->attributes->get('auth_user');

        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected,cancelled'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $requests = HallRentalRequest::query()
            ->with('hall')
            ->whereHas('hall', fn (Builder $query) => $query->where('venue_owner_id', $venueOwner['id']))
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

    public function update(Request $request, int $id): JsonResponse
    {
        $venueOwner = $request->attributes->get('auth_user');

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'response_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $rentalRequest = HallRentalRequest::query()
            ->with('hall')
            ->whereKey($id)
            ->whereHas('hall', fn (Builder $query) => $query->where('venue_owner_id', $venueOwner['id']))
            ->firstOrFail();

        if ($validated['status'] === HallRentalRequest::STATUS_APPROVED) {
            $this->ensureNoApprovedOverlap($rentalRequest);
        }

        if (! in_array($rentalRequest->status, [HallRentalRequest::STATUS_PENDING, HallRentalRequest::STATUS_REJECTED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Можно изменять только новые или ранее отклоненные заявки.'],
            ]);
        }

        $rentalRequest->update([
            'status' => $validated['status'],
            'response_note' => $validated['response_note'] ?? null,
            'responded_at' => now(),
        ]);

        return response()->json([
            'message' => $validated['status'] === HallRentalRequest::STATUS_APPROVED
                ? 'Заявка на аренду подтверждена.'
                : 'Заявка на аренду отклонена.',
            'rental_request' => $this->transformRequest($rentalRequest->fresh('hall')),
        ]);
    }

    private function ensureNoApprovedOverlap(HallRentalRequest $rentalRequest): void
    {
        $hasOverlap = HallRentalRequest::query()
            ->approved()
            ->where('hall_id', $rentalRequest->hall_id)
            ->whereKeyNot($rentalRequest->id)
            ->where('requested_start', '<', $rentalRequest->requested_end)
            ->where('requested_end', '>', $rentalRequest->requested_start)
            ->exists();

        if ($hasOverlap) {
            throw ValidationException::withMessages([
                'status' => ['На этот интервал уже есть подтвержденная аренда.'],
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
