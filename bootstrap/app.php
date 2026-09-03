<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
        | Behind the nginx front door, the request Laravel sees arrives from
        | loopback over plain HTTP. Without this it builds every redirect and
        | asset link as `http://127.0.0.1:8000/...` — which is why a proxied
        | login bounces the browser somewhere it cannot reach.
        |
        | Trust is limited to loopback and the VPC range rather than `*`: an
        | X-Forwarded-For from anywhere else is a client asserting its own IP,
        | and the rate limiters key on that.
        */
        $middleware->trustProxies(at: [
            '127.0.0.1',
            '::1',
            '172.16.0.0/12',
        ]);

        $middleware->alias([
            'auth.api' => \App\Http\Middleware\EnsureBearerAuth::class,
            'module' => \App\Http\Middleware\EnsureModuleAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
