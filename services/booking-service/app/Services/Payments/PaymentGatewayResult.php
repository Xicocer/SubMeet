<?php

namespace App\Services\Payments;

class PaymentGatewayResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $providerPaymentId,
        public readonly string $status,
        public readonly ?string $confirmationUrl,
        public readonly bool $isPaid,
        public readonly bool $isCancelled,
        public readonly ?string $failureReason,
        public readonly array $payload,
    ) {
    }
}
