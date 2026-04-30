<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizerUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('auth_user');
        $role = data_get($user, 'role.role');

        if ($role !== 'organizer') {
            return response()->json([
                'message' => 'Organizer access is required.',
            ], 403);
        }

        return $next($request);
    }
}
