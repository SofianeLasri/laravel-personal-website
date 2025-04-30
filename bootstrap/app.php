<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleDashboardInertiaRequests;
use App\Http\Middleware\HandlePublicInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use SlProjects\LaravelRequestLogger\app\Http\Middleware\SaveRequestMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandlePublicInertiaRequests::class,
            HandleDashboardInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SaveRequestMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
