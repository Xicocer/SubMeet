<?php

namespace App\Http\Middleware;

use App\Services\AuthServiceClient;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateOrganizer
{
    public function __construct(
        private readonly AuthServiceClient $authServiceClient,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'message' => 'Authorization token is required.',
            ], 401);
        }

        try {
            $user = $this->authServiceClient->getCurrentUser($token);
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'Auth service is temporarily unavailable.',
            ], 503);
        }

        if (!$user) {
            return response()->json([
                'message' => 'User is not authenticated.',
            ], 401);
        }

        if (($user['role']['role'] ?? null) !== 'organizer') {
            return response()->json([
                'message' => 'Only organizers can access hall management.',
            ], 403);
        }

        $request->attributes->set('auth_user', $user);

        return $next($request);
    }
}
