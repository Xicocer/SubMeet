<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthServiceClient;
use App\Services\BookingServiceClient;
use App\Services\EventServiceClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AuthServiceClient $authServiceClient,
        private readonly EventServiceClient $eventServiceClient,
        private readonly BookingServiceClient $bookingServiceClient,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $token = $this->requireBearerToken($request);

        try {
            $authDashboard = $this->authServiceClient->getDashboard($token)->throw()->json();
            $eventDashboard = $this->eventServiceClient->getDashboard($token)->throw()->json();
            $bookingDashboard = $this->bookingServiceClient->getDashboard($token)->throw()->json();
        } catch (ConnectionException $exception) {
            throw new ServiceUnavailableHttpException(null, 'A dependent service is unavailable.', $exception);
        }

        return response()->json([
            'metrics' => array_merge(
                $authDashboard['metrics'] ?? [],
                $eventDashboard['metrics'] ?? [],
                $bookingDashboard['metrics'] ?? [],
            ),
            'booking' => [
                'recent_problem_payments' => $bookingDashboard['recent_problem_payments'] ?? [],
            ],
        ]);
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
