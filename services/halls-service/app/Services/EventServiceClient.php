<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;

class EventServiceClient
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws ConnectionException
     */
    public function getHallUsage(int $hallId): ?array
    {
        $response = $this->http
            ->acceptJson()
            ->withHeaders([
                'X-Internal-Api-Key' => (string) config('services.events.internal_api_key'),
            ])
            ->get(rtrim(config('services.events.base_url'), '/') . '/internal/halls/' . $hallId . '/usage');

        if ($response->notFound() || $response->unauthorized() || $response->forbidden()) {
            return null;
        }

        if ($response->failed()) {
            $response->throw();
        }

        return $response->json();
    }
}
