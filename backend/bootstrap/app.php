<?php

use App\Http\Middleware\RejectPublicDemoOperation;
use App\Modules\Identity\Interfaces\Http\Middleware\EnsureGuestApi;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = env('TRUSTED_PROXIES');
        if (is_string($trustedProxies) && $trustedProxies !== '') {
            $middleware->trustProxies(
                at: $trustedProxies,
                headers: Request::HEADER_X_FORWARDED_AWS_ELB,
            );
        }

        $middleware->statefulApi();
        $middleware->alias([
            'public-demo.restrict' => RejectPublicDemoOperation::class,
        ]);
        $middleware->prependToPriorityList(
            ThrottleRequests::class,
            EnsureGuestApi::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
