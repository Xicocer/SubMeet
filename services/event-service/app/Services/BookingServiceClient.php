<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;

class BookingServiceClient
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
    public function getOrganizerEventBookingImpact(string $token, int $eventId): ?array
    {
        return $this->requestOrganizerGuardPayload(
            $token,
            '/organizer/guards/events/' . $eventId . '/booking-impact'
        );
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws ConnectionException
     */
    public function getOrganizerSessionBookingImpact(string $token, int $sessionId): ?array
    {
        return $this->requestOrganizerGuardPayload(
            $token,
            '/organizer/guards/sessions/' . $sessionId . '/booking-impact'
        );
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws ConnectionException
     */
    private function requestOrganizerGuardPayload(string $token, string $path): ?array
    {
        $response = $this->http
            ->acceptJson()
            ->withToken($token)
            ->get(rtrim(config('services.booking.base_url'), '/') . $path);

        if ($response->unauthorized() || $response->forbidden() || $response->notFound()) {
            return null;
        }

        if ($response->failed()) {
            $response->throw();
        }

        return $response->json();
    }
}
