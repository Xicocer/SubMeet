<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\TransformsBookingPayloads;
use App\Http\Controllers\Controller;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketVerificationController extends Controller
{
    use TransformsBookingPayloads;

    public function __construct(
        private readonly BookingService $bookingService,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $validated = $request->validate([
            'ticket_code' => ['required', 'string', 'max:5000'],
        ]);

        $result = $this->bookingService->verifyTicketForOrganizer(
            authUser: $authUser,
            rawTicketPayload: $validated['ticket_code'],
        );

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
            'booking' => $result['booking'] ? $this->transformBooking($result['booking']) : null,
        ]);
    }
}
