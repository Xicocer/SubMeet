<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogHttpRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->headers->get('X-Request-Id') ?: (string) Str::uuid();

        $request->headers->set('X-Request-Id', $requestId);

        Log::withContext([
            'request_id' => $requestId,
            'service' => config('app.name'),
        ]);

        $startedAt = microtime(true);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->writeRequestLog(
                request: $request,
                requestId: $requestId,
                statusCode: 500,
                durationMs: (int) round((microtime(true) - $startedAt) * 1000),
                errorMessage: $exception->getMessage(),
            );

            throw $exception;
        }

        $response->headers->set('X-Request-Id', $requestId);

        $this->writeRequestLog(
            request: $request,
            requestId: $requestId,
            statusCode: $response->getStatusCode(),
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
        );

        return $response;
    }

    private function writeRequestLog(
        Request $request,
        string $requestId,
        int $statusCode,
        int $durationMs,
        ?string $errorMessage = null,
    ): void {
        if ($request->path() === 'up') {
            return;
        }

        $route = $request->route();

        Log::info('http_request', [
            'service' => config('app.name'),
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => '/' . ltrim($request->path(), '/'),
            'query_string' => $request->getQueryString(),
            'route_name' => $route?->getName(),
            'route_uri' => $route && method_exists($route, 'uri') ? $route->uri() : null,
            'status_code' => $statusCode,
            'duration_ms' => $durationMs,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->id ?? data_get($request->attributes->get('auth_user'), 'id'),
            'error' => $errorMessage,
        ]);
    }
}
