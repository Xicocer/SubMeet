<?php

namespace App\Services\Payments;

interface PaymentGatewayInterface
{
    public function providerName(): string;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function createPayment(
        string $idempotenceKey,
        float $amount,
        string $currency,
        string $description,
        string $returnUrl,
        array $metadata = [],
    ): PaymentGatewayResult;

    public function getPayment(string $providerPaymentId): PaymentGatewayResult;
}
