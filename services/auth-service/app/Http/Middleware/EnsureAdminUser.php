<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user()?->loadMissing('role');

        if (($user?->role?->role ?? null) !== 'admin') {
            return response()->json([
                'message' => 'Доступ разрешен только администраторам.',
            ], 403);
        }

        return $next($request);
    }
}
