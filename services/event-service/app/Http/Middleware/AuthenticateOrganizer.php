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
                'message' => 'Требуется токен авторизации.',
            ], 401);
        }

        try {
            $user = $this->authServiceClient->getCurrentUser($token);
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'Auth-service временно недоступен.',
            ], 503);
        }

        if (!$user) {
            return response()->json([
                'message' => 'Пользователь не авторизован.',
            ], 401);
        }

        if (($user['role']['role'] ?? null) !== 'organizer') {
            return response()->json([
                'message' => 'Доступ разрешен только организаторам.',
            ], 403);
        }

        $request->attributes->set('auth_user', $user);

        return $next($request);
    }
}
