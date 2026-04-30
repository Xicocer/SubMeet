<?php

namespace App\Services\Payments;

use Illuminate\Support\Str;

class MockPaymentGateway implements PaymentGatewayInterface
{
    public function providerName(): string
    {
        return 'mock';
    }

    public function createPayment(
        string $idempotenceKey,
        float $amount,
        string $currency,
        string $description,
        string $returnUrl,
        array $metadata = [],
    ): PaymentGatewayResult {
        return new PaymentGatewayResult(
            providerPaymentId: (string) Str::uuid(),
            status: 'pending',
            confirmationUrl: $returnUrl,
            isPaid: false,
            isCancelled: false,
            failureReason: null,
            payload: [
                'gateway' => 'mock',
                'description' => $description,
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => $currency,
                'return_url' => $returnUrl,
                'metadata' => $metadata,
            ],
        );
    }

    public function getPayment(string $providerPaymentId): PaymentGatewayResult
    {
        return new PaymentGatewayResult(
            providerPaymentId: $providerPaymentId,
            status: 'succeeded',
            confirmationUrl: null,
            isPaid: true,
            isCancelled: false,
            failureReason: null,
            payload: [
                'gateway' => 'mock',
                'provider_payment_id' => $providerPaymentId,
                'message' => 'Mock payment has been marked as succeeded during status refresh.',
            ],
        );
    }
}
