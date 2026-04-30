<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {
    }

    public function yookassa(Request $request): JsonResponse
    {
        $providerPaymentId = data_get($request->all(), 'object.id');

        if (!is_string($providerPaymentId) || trim($providerPaymentId) === '') {
            return response()->json(['status' => 'ignored'], 202);
        }

        $this->bookingService->syncPaymentByProviderReference($providerPaymentId);

        return response()->json(['status' => 'ok']);
    }
}
