<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\TransformsBookingPayloads;
use App\Http\Controllers\Controller;
use App\Services\BookingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use TransformsBookingPayloads;

    public function __construct(
        private readonly BookingService $bookingService,
    ) {
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        try {
            $booking = $this->bookingService->initiatePaymentForBooking($authUser, $id);
        } catch (ModelNotFoundException) {
            abort(404, 'Booking not found.');
        }

        return response()->json([
            'message' => 'Payment checkout has been started successfully.',
            'checkout_required' => true,
            'booking' => $this->transformBooking($booking),
        ]);
    }

    public function refresh(Request $request, int $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        try {
            $booking = $this->bookingService->refreshPaymentForBooking($authUser, $id);
        } catch (ModelNotFoundException) {
            abort(404, 'Booking not found.');
        }

        return response()->json([
            'message' => 'Payment status has been refreshed successfully.',
            'booking' => $this->transformBooking($booking),
        ]);
    }
}
