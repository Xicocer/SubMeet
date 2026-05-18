<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

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

    /**
     * @throws ConnectionException
     */
    public function getDashboard(string $token): Response
    {
        return $this->sendAuthorizedRequest($token, 'GET', '/admin/dashboard');
    }

    /**
     * @throws ConnectionException
     */
    public function getOrganizers(string $token, array $query = []): Response
    {
        return $this->sendAuthorizedRequest($token, 'GET', '/admin/organizers', ['query' => $query]);
    }

    /**
     * @throws ConnectionException
     */
    public function updateOrganizerModeration(string $token, int $id, array $payload): Response
    {
        return $this->sendAuthorizedRequest($token, 'PATCH', '/admin/organizers/' . $id . '/moderation', [
            'json' => $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @throws ConnectionException
     */
    private function sendAuthorizedRequest(string $token, string $method, string $path, array $options = []): Response
    {
        return $this->http
            ->acceptJson()
            ->withToken($token)
            ->send($method, rtrim(config('services.auth.base_url'), '/') . $path, $options);
    }
}
