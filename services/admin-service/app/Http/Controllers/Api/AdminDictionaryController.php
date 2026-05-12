<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EventServiceClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class AdminDictionaryController extends Controller
{
    public function __construct(
        private readonly EventServiceClient $eventServiceClient,
    ) {
    }

    public function categories(Request $request): JsonResponse
    {
        return $this->proxy($request, fn (string $token) => $this->eventServiceClient->getCategories($token));
    }

    public function storeCategory(Request $request): JsonResponse
    {
        return $this->proxy($request, fn (string $token) => $this->eventServiceClient->storeCategory($token, $request->all()));
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        return $this->proxy($request, fn (string $token) => $this->eventServiceClient->updateCategory($token, $id, $request->all()));
    }

    public function destroyCategory(Request $request, int $id): JsonResponse
    {
        return $this->proxy($request, fn (string $token) => $this->eventServiceClient->destroyCategory($token, $id));
    }

    public function ageRatings(Request $request): JsonResponse
    {
        return $this->proxy($request, fn (string $token) => $this->eventServiceClient->getAgeRatings($token));
    }

    public function storeAgeRating(Request $request): JsonResponse
    {
        return $this->proxy($request, fn (string $token) => $this->eventServiceClient->storeAgeRating($token, $request->all()));
    }

    public function updateAgeRating(Request $request, int $id): JsonResponse
    {
        return $this->proxy($request, fn (string $token) => $this->eventServiceClient->updateAgeRating($token, $id, $request->all()));
    }

    public function destroyAgeRating(Request $request, int $id): JsonResponse
    {
        return $this->proxy($request, fn (string $token) => $this->eventServiceClient->destroyAgeRating($token, $id));
    }

    public function tags(Request $request): JsonResponse
    {
        return $this->proxy($request, fn (string $token) => $this->eventServiceClient->getTags($token));
    }

    public function storeTag(Request $request): JsonResponse
    {
        return $this->proxy($request, fn (string $token) => $this->eventServiceClient->storeTag($token, $request->all()));
    }

    public function updateTag(Request $request, int $id): JsonResponse
    {
        return $this->proxy($request, fn (string $token) => $this->eventServiceClient->updateTag($token, $id, $request->all()));
    }

    public function destroyTag(Request $request, int $id): JsonResponse
    {
        return $this->proxy($request, fn (string $token) => $this->eventServiceClient->destroyTag($token, $id));
    }

    /**
     * @param  callable(string): \Illuminate\Http\Client\Response  $callback
     */
    private function proxy(Request $request, callable $callback): JsonResponse
    {
        $token = $this->requireBearerToken($request);

        try {
            $response = $callback($token);
        } catch (ConnectionException $exception) {
            throw new ServiceUnavailableHttpException(null, 'Event service is unavailable.', $exception);
        }

        return response()->json($response->json(), $response->status());
    }

    private function requireBearerToken(Request $request): string
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            abort(401, 'Missing bearer token.');
        }

        return $token;
    }
}
