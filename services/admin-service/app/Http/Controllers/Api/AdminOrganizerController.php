<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthServiceClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class AdminOrganizerController extends Controller
{
    public function __construct(
        private readonly AuthServiceClient $authServiceClient,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $token = $this->requireBearerToken($request);

        try {
            $response = $this->authServiceClient
                ->getOrganizers($token, $request->only(['status', 'search', 'page', 'per_page']));
        } catch (ConnectionException $exception) {
            throw new ServiceUnavailableHttpException(null, 'Auth service is unavailable.', $exception);
        }

        return response()->json($response->json(), $response->status());
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $token = $this->requireBearerToken($request);

        try {
            $response = $this->authServiceClient->updateOrganizerModeration($token, $id, $request->all());
        } catch (ConnectionException $exception) {
            throw new ServiceUnavailableHttpException(null, 'Auth service is unavailable.', $exception);
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
