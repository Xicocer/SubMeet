<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\TransformsBookingPayloads;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class BookingController extends Controller
{
    use TransformsBookingPayloads;

    public function __construct(
        private readonly BookingService $bookingService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $bookings = Booking::query()
            ->with(['snapshot', 'items.seat', 'items.standingArea', 'payment'])
            ->where('user_id', (int) $authUser['id'])
            ->latest('id')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString()
            ->through(fn (Booking $booking) => $this->transformBooking($booking));

        return response()->json($bookings);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        $booking = Booking::query()
            ->with(['snapshot', 'items.seat', 'items.standingArea', 'payment'])
            ->whereKey($id)
            ->where('user_id', (int) $authUser['id'])
            ->firstOrFail();

        return response()->json($this->transformBooking($booking));
    }

    public function store(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $validated = $this->validateBookingPayload($request);

        try {
            $booking = $this->bookingService->reserveBooking(
                authUser: $authUser,
                sessionId: (int) $validated['session_id'],
                seatElementIds: $validated['seat_ids'] ?? [],
                standingSelections: $validated['standing'] ?? [],
            );
        } catch (ModelNotFoundException) {
            abort(404, 'Session is not available for booking.');
        } catch (ConnectionException|RequestException $exception) {
            throw new ServiceUnavailableHttpException(null, 'Upstream service is unavailable.', $exception);
        }

        return response()->json([
            'message' => 'Booking created and seats reserved successfully.',
            'checkout_required' => false,
            'booking' => $this->transformBooking($booking),
        ], 201);
    }

    public function purchase(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $validated = $this->validateBookingPayload($request);

        try {
            $booking = $this->bookingService->purchaseBooking(
                authUser: $authUser,
                sessionId: (int) $validated['session_id'],
                seatElementIds: $validated['seat_ids'] ?? [],
                standingSelections: $validated['standing'] ?? [],
            );
        } catch (ModelNotFoundException) {
            abort(404, 'Session is not available for booking.');
        } catch (ConnectionException|RequestException $exception) {
            throw new ServiceUnavailableHttpException(null, 'Upstream service is unavailable.', $exception);
        }

        return response()->json([
            'message' => 'Purchase checkout has been started successfully.',
            'checkout_required' => true,
            'booking' => $this->transformBooking($booking),
        ], 201);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        try {
            $booking = $this->bookingService->cancelBooking($authUser, $id);
        } catch (ModelNotFoundException) {
            abort(404, 'Booking not found.');
        }

        return response()->json([
            'message' => 'Booking cancelled successfully.',
            'booking' => $this->transformBooking($booking),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBookingPayload(Request $request): array
    {
        return $request->validate([
            'session_id' => ['required', 'integer', 'min:1'],
            'seat_ids' => ['nullable', 'array'],
            'seat_ids.*' => ['required', 'string', 'max:100'],
            'standing' => ['nullable', 'array'],
            'standing.*.element_id' => ['required', 'string', 'max:100'],
            'standing.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
    }
}
