<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\LogHttpRequests::class);

        $middleware->alias([
            'api.auth' => \App\Http\Middleware\AuthenticateApiUser::class,
            'admin.auth' => \App\Http\Middleware\AuthenticateAdmin::class,
            'api.organizer' => \App\Http\Middleware\EnsureOrganizerUser::class,
            'internal.api' => \App\Http\Middleware\EnsureInternalApiAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
