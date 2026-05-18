<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;

class AuthServiceClient
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
    public function getCurrentUser(string $token): ?array
    {
        $response = $this->http
            ->acceptJson()
            ->withToken($token)
            ->connectTimeout((float) config('services.auth.connect_timeout', 2))
            ->timeout((float) config('services.auth.timeout', 8))
            ->get(rtrim(config('services.auth.base_url'), '/') . '/me');

        if ($response->unauthorized() || $response->forbidden()) {
            return null;
        }

        if ($response->failed()) {
            $response->throw();
        }

        return $response->json('user');
    }
}
