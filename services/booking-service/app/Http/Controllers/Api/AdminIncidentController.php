<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminIncidentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:all,failed,cancelled'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = $validated['status'] ?? 'all';

        $incidents = Payment::query()
            ->with(['booking.snapshot'])
            ->when(
                $status !== 'all',
                fn ($query) => $query->where('status', $status),
                fn ($query) => $query->whereIn('status', [Payment::STATUS_FAILED, Payment::STATUS_CANCELLED])
            )
            ->when(
                $validated['search'] ?? null,
                function ($query, string $search) {
                    $search = trim($search);
                    $like = '%' . $search . '%';

                    $query->where(function ($builder) use ($search, $like) {
                        if (is_numeric($search)) {
                            $builder->orWhere('booking_id', (int) $search);
                        }

                        $builder
                            ->orWhere('external_reference', 'like', $like)
                            ->orWhere('provider', 'like', $like)
                            ->orWhere('failure_reason', 'like', $like)
                            ->orWhereHas('booking.snapshot', function ($snapshotQuery) use ($like) {
                                $snapshotQuery
                                    ->where('event_title', 'like', $like)
                                    ->orWhere('hall_name', 'like', $like);
                            });
                    });
                }
            )
            ->latest('updated_at')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString()
            ->through(fn (Payment $payment) => $this->transformPayment($payment));

        return response()->json($incidents);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformPayment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'booking_id' => $payment->booking_id,
            'provider' => $payment->provider,
            'status' => $payment->status,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'external_reference' => $payment->external_reference,
            'failure_reason' => $payment->failure_reason,
            'created_at' => $payment->created_at?->toISOString(),
            'updated_at' => $payment->updated_at?->toISOString(),
            'paid_at' => $payment->paid_at?->toISOString(),
            'cancelled_at' => $payment->cancelled_at?->toISOString(),
            'booking' => [
                'status' => $payment->booking?->status,
                'flow_type' => $payment->booking?->flow_type,
                'confirmed_at' => $payment->booking?->confirmed_at?->toISOString(),
            ],
            'snapshot' => [
                'event_title' => $payment->booking?->snapshot?->event_title,
                'hall_name' => $payment->booking?->snapshot?->hall_name,
            ],
        ];
    }
}
