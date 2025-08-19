<?php

use App\Http\Controllers\ErrorController;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleDashboardInertiaRequests;
use App\Http\Middleware\HandlePublicInertiaRequests;
use App\Http\Middleware\RestrictRegistration;
use App\Http\Middleware\SetLocaleFromAcceptLanguage;
use App\Http\Middleware\TriggerBotAnalysis;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Inertia\Inertia;
use Sentry\Laravel\Integration;
use SlProjects\LaravelRequestLogger\app\Http\Middleware\SaveRequestMiddleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance']);

        $middleware->alias([
            'restrict.registration' => RestrictRegistration::class,
            'bot.analyze' => TriggerBotAnalysis::class,
        ]);

        $middleware->web(append: [
            SetLocaleFromAcceptLanguage::class,
            HandleAppearance::class,
            HandlePublicInertiaRequests::class,
            HandleDashboardInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SaveRequestMiddleware::class,
            TriggerBotAnalysis::class,
        ]);

        // For Caddy reverse proxy
        $middleware->trustProxies(at: '*');
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Integration::handles($exceptions);

        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if (! $request->expectsJson() && ! $request->is('dashboard*')) {
                Inertia::setRootView('public-app');

                $controller = new ErrorController;
                $inertiaResponse = $controller->show404($request);
                $response = $inertiaResponse->toResponse($request);
                $response->setStatusCode(404);

                return $response;
            }

            return null;
        });
    })->create();
