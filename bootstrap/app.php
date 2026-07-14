<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'portal.basic' => \App\Http\Middleware\PortalBasicAuth::class,
            'plan' => \App\Http\Middleware\EnsurePlanFeature::class,
        ]);

        $middleware->appendToGroup('api', \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class);

        // iyzico callback/webhook dış servisten POST edilir; CSRF muaf.
        $middleware->validateCsrfTokens(except: ['billing/callback/*', 'billing/iyzico/webhook']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
