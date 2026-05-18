<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;

class HallServiceClient
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
    public function getHall(int $hallId): ?array
    {
        $response = $this->http
            ->acceptJson()
            ->get(rtrim(config('services.halls.base_url'), '/') . '/halls/' . $hallId);

        if ($response->notFound()) {
            return null;
        }

        if ($response->failed()) {
            $response->throw();
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws ConnectionException
     */
    public function getOrganizerHall(string $token, int $hallId): ?array
    {
        $response = $this->http
            ->acceptJson()
            ->withToken($token)
            ->get(rtrim(config('services.halls.base_url'), '/') . '/organizer/halls/' . $hallId);

        if ($response->notFound() || $response->unauthorized() || $response->forbidden()) {
            return null;
        }

        if ($response->failed()) {
            $response->throw();
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws ConnectionException
     */
    public function getOrganizerRentalRequest(string $token, int $requestId): ?array
    {
        $response = $this->http
            ->acceptJson()
            ->withToken($token)
            ->get(rtrim(config('services.halls.base_url'), '/') . '/organizer/hall-rental-requests/' . $requestId);

        if ($response->notFound() || $response->unauthorized() || $response->forbidden()) {
            return null;
        }

        if ($response->failed()) {
            $response->throw();
        }

        return $response->json();
    }
}
