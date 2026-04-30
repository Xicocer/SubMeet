<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\TransformsBookingPayloads;
use App\Http\Controllers\Controller;
use App\Services\BookingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class SessionAvailabilityController extends Controller
{
    use TransformsBookingPayloads;

    public function __construct(
        private readonly BookingService $bookingService,
    ) {
    }

    public function show(int $id): JsonResponse
    {
        try {
            $snapshot = $this->bookingService->getAvailabilityBySessionId($id);
        } catch (ModelNotFoundException) {
            abort(404, 'Session is not available for booking.');
        } catch (ConnectionException|RequestException $exception) {
            throw new ServiceUnavailableHttpException(null, 'Upstream service is unavailable.', $exception);
        }

        return response()
            ->json($this->transformAvailability($snapshot))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
