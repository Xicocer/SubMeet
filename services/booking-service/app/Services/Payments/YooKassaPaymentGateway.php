<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;

class YooKassaPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    public function providerName(): string
    {
        return 'yookassa';
    }

    public function createPayment(
        string $idempotenceKey,
        float $amount,
        string $currency,
        string $description,
        string $returnUrl,
        array $metadata = [],
    ): PaymentGatewayResult {
        $response = $this->client()
            ->withHeaders([
                'Idempotence-Key' => $idempotenceKey,
            ])
            ->post('/payments', [
                'amount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency' => $currency,
                ],
                'capture' => (bool) config('payments.yookassa.capture', true),
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => $returnUrl,
                ],
                'description' => $description,
                'metadata' => $metadata,
            ]);

        if ($response->failed()) {
            $response->throw();
        }

        return $this->mapResponse($response->json() ?? []);
    }

    public function getPayment(string $providerPaymentId): PaymentGatewayResult
    {
        $response = $this->client()->get('/payments/' . $providerPaymentId);

        if ($response->failed()) {
            $response->throw();
        }

        return $this->mapResponse($response->json() ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function extractPaymentObject(array $payload): array
    {
        $object = $payload['object'] ?? $payload;

        return is_array($object) ? $object : [];
    }

    /**
     * @return PaymentGatewayResult
     */
    public function mapWebhookPayload(array $payload): PaymentGatewayResult
    {
        return $this->mapResponse($this->extractPaymentObject($payload));
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        $shopId = trim((string) config('payments.yookassa.shop_id'));
        $secretKey = trim((string) config('payments.yookassa.secret_key'));

        if ($shopId === '' || $secretKey === '') {
            throw new \RuntimeException('YooKassa credentials are not configured.');
        }

        return [$shopId, $secretKey];
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        [$shopId, $secretKey] = $this->credentials();

        return $this->http
            ->acceptJson()
            ->asJson()
            ->baseUrl(rtrim((string) config('payments.yookassa.base_url'), '/'))
            ->withBasicAuth($shopId, $secretKey);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapResponse(array $payload): PaymentGatewayResult
    {
        $status = (string) Arr::get($payload, 'status', 'pending');
        $confirmationUrl = Arr::get($payload, 'confirmation.confirmation_url');
        $cancellationReason = Arr::get($payload, 'cancellation_details.reason');
        $paid = (bool) Arr::get($payload, 'paid', false);

        return new PaymentGatewayResult(
            providerPaymentId: (string) Arr::get($payload, 'id', ''),
            status: $status,
            confirmationUrl: is_string($confirmationUrl) ? $confirmationUrl : null,
            isPaid: $paid || $status === 'succeeded',
            isCancelled: $status === 'canceled',
            failureReason: is_string($cancellationReason) ? $cancellationReason : null,
            payload: $payload,
        );
    }
}
