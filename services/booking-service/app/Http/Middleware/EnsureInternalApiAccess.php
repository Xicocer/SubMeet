<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalApiAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = (string) config('services.internal.api_key', 'submeet-internal-key');
        $providedKey = (string) $request->header('X-Internal-Api-Key', '');

        if ($expectedKey === '' || !hash_equals($expectedKey, $providedKey)) {
            return response()->json([
                'message' => 'Internal API access is forbidden.',
            ], 403);
        }

        return $next($request);
    }
}
