<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

class BookingServiceClient
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    /**
     * @throws ConnectionException
     */
    public function getDashboard(string $token): Response
    {
        return $this->http
            ->acceptJson()
            ->withToken($token)
            ->get(rtrim(config('services.booking.base_url'), '/') . '/admin/dashboard');
    }

    /**
     * @throws ConnectionException
     */
    public function getIncidents(string $token, array $query = []): Response
    {
        return $this->http
            ->acceptJson()
            ->withToken($token)
            ->get(rtrim(config('services.booking.base_url'), '/') . '/admin/incidents', $query);
    }
}
