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
                loyaltyPointsToSpend: (int) ($validated['loyalty_points_to_spend'] ?? 0),
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

    public function guestPurchase(Request $request): JsonResponse
    {
        $validated = $this->validateBookingPayload($request, guest: true);

        try {
            $booking = $this->bookingService->purchaseGuestBooking(
                customerEmail: $validated['customer_email'],
                guestBirthDate: $validated['guest_birth_date'] ?? null,
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
            'message' => 'Guest purchase checkout has been started successfully.',
            'checkout_required' => true,
            'booking' => $this->transformBooking($booking, includeGuestAccessToken: true),
        ], 201);
    }

    public function guestShow(Request $request, int $id): JsonResponse
    {
        $booking = $this->findGuestBooking($request, $id);

        return response()->json($this->transformBooking($booking, includeGuestAccessToken: true));
    }

    public function guestRefreshPayment(Request $request, int $id): JsonResponse
    {
        $token = $this->guestToken($request);

        try {
            $booking = $this->bookingService->refreshGuestPaymentForBooking($id, $token);
        } catch (ModelNotFoundException) {
            abort(404, 'Booking not found.');
        }

        return response()->json([
            'message' => 'Payment status has been refreshed successfully.',
            'booking' => $this->transformBooking($booking, includeGuestAccessToken: true),
        ]);
    }

    public function guestCancel(Request $request, int $id): JsonResponse
    {
        $token = $this->guestToken($request);

        try {
            $booking = $this->bookingService->cancelGuestBooking($id, $token);
        } catch (ModelNotFoundException) {
            abort(404, 'Booking not found.');
        }

        return response()->json([
            'message' => 'Booking cancelled successfully.',
            'booking' => $this->transformBooking($booking, includeGuestAccessToken: true),
        ]);
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
    public function loyalty(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $account = $this->bookingService->getLoyaltyAccountForUser((int) $authUser['id']);

        return response()->json([
            'balance' => $account->balance,
            'earned_total' => $account->earned_total,
            'spent_total' => $account->spent_total,
            'earn_percent' => (float) config('booking.loyalty_earn_percent', 15),
            'max_discount_percent' => (float) config('booking.loyalty_max_discount_percent', 80),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBookingPayload(Request $request, bool $guest = false): array
    {
        return $request->validate([
            'session_id' => ['required', 'integer', 'min:1'],
            'seat_ids' => ['nullable', 'array'],
            'seat_ids.*' => ['required', 'string', 'max:100'],
            'standing' => ['nullable', 'array'],
            'standing.*.element_id' => ['required', 'string', 'max:100'],
            'standing.*.quantity' => ['required', 'integer', 'min:1'],
            'loyalty_points_to_spend' => [$guest ? 'prohibited' : 'nullable', 'integer', 'min:0'],
            'customer_email' => [$guest ? 'required' : 'prohibited', 'email:rfc', 'max:255'],
            'guest_birth_date' => [$guest ? 'nullable' : 'prohibited', 'date', 'before:today'],
        ]);
    }

    private function findGuestBooking(Request $request, int $id): Booking
    {
        $token = $this->guestToken($request);

        return Booking::query()
            ->with(['snapshot', 'items.seat', 'items.standingArea', 'payment'])
            ->whereKey($id)
            ->where('guest_access_token', $token)
            ->whereNull('user_id')
            ->firstOrFail();
    }

    private function guestToken(Request $request): string
    {
        $token = trim((string) ($request->header('X-Guest-Access-Token') ?: $request->input('token') ?: $request->query('token')));

        if ($token === '' || strlen($token) > 96) {
            abort(403, 'Guest access token is required.');
        }

        return $token;
    }
}
