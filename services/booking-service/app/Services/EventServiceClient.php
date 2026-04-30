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
    public function getSession(int $sessionId): ?array
    {
        $response = $this->http
            ->acceptJson()
            ->get(rtrim(config('services.events.base_url'), '/') . '/sessions/' . $sessionId);

        if ($response->notFound()) {
            return null;
        }

        if ($response->failed()) {
            $response->throw();
        }

        return $response->json();
    }
}
