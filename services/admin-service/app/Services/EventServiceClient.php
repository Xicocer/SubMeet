<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

class EventServiceClient
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
        return $this->sendAuthorizedRequest($token, 'GET', '/admin/dashboard');
    }

    /**
     * @throws ConnectionException
     */
    public function getEvents(string $token, array $query = []): Response
    {
        return $this->sendAuthorizedRequest($token, 'GET', '/admin/events', ['query' => $query]);
    }

    /**
     * @throws ConnectionException
     */
    public function updateEventModeration(string $token, int $id, array $payload): Response
    {
        return $this->sendAuthorizedRequest($token, 'PATCH', '/admin/events/' . $id . '/moderation', [
            'json' => $payload,
        ]);
    }

    /**
     * @throws ConnectionException
     */
    public function getCategories(string $token): Response
    {
        return $this->sendAuthorizedRequest($token, 'GET', '/admin/categories');
    }

    /**
     * @throws ConnectionException
     */
    public function storeCategory(string $token, array $payload): Response
    {
        return $this->sendAuthorizedRequest($token, 'POST', '/admin/categories', ['json' => $payload]);
    }

    /**
     * @throws ConnectionException
     */
    public function updateCategory(string $token, int $id, array $payload): Response
    {
        return $this->sendAuthorizedRequest($token, 'PUT', '/admin/categories/' . $id, ['json' => $payload]);
    }

    /**
     * @throws ConnectionException
     */
    public function destroyCategory(string $token, int $id): Response
    {
        return $this->sendAuthorizedRequest($token, 'DELETE', '/admin/categories/' . $id);
    }

    /**
     * @throws ConnectionException
     */
    public function getAgeRatings(string $token): Response
    {
        return $this->sendAuthorizedRequest($token, 'GET', '/admin/age-ratings');
    }

    /**
     * @throws ConnectionException
     */
    public function storeAgeRating(string $token, array $payload): Response
    {
        return $this->sendAuthorizedRequest($token, 'POST', '/admin/age-ratings', ['json' => $payload]);
    }

    /**
     * @throws ConnectionException
     */
    public function updateAgeRating(string $token, int $id, array $payload): Response
    {
        return $this->sendAuthorizedRequest($token, 'PUT', '/admin/age-ratings/' . $id, ['json' => $payload]);
    }

    /**
     * @throws ConnectionException
     */
    public function destroyAgeRating(string $token, int $id): Response
    {
        return $this->sendAuthorizedRequest($token, 'DELETE', '/admin/age-ratings/' . $id);
    }

    /**
     * @throws ConnectionException
     */
    public function getTags(string $token): Response
    {
        return $this->sendAuthorizedRequest($token, 'GET', '/admin/tags');
    }

    /**
     * @throws ConnectionException
     */
    public function storeTag(string $token, array $payload): Response
    {
        return $this->sendAuthorizedRequest($token, 'POST', '/admin/tags', ['json' => $payload]);
    }

    /**
     * @throws ConnectionException
     */
    public function updateTag(string $token, int $id, array $payload): Response
    {
        return $this->sendAuthorizedRequest($token, 'PUT', '/admin/tags/' . $id, ['json' => $payload]);
    }

    /**
     * @throws ConnectionException
     */
    public function destroyTag(string $token, int $id): Response
    {
        return $this->sendAuthorizedRequest($token, 'DELETE', '/admin/tags/' . $id);
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
            ->send($method, rtrim(config('services.events.base_url'), '/') . $path, $options);
    }
}
