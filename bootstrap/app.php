<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Tenant resolver sur toutes les routes API
        $middleware->api(prepend: [
            \App\Http\Middleware\TenantResolver::class,
        ]);

        // Alias pour middleware de route
        $middleware->alias([
            'role'        => \App\Http\Middleware\CheckRole::class,
            'zone.access' => \App\Http\Middleware\CheckZoneAccess::class,
        ]);

        // Sanctum : auth stateful (cookies)
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
