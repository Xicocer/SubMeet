<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BookingServiceClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class AdminIncidentController extends Controller
{
    public function __construct(
        private readonly BookingServiceClient $bookingServiceClient,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $token = $this->requireBearerToken($request);

        try {
            $response = $this->bookingServiceClient
                ->getIncidents($token, $request->only(['status', 'search', 'page', 'per_page']));
        } catch (ConnectionException $exception) {
            throw new ServiceUnavailableHttpException(null, 'Booking service is unavailable.', $exception);
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
