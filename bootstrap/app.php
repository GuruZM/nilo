<?php

use App\Http\Middleware\EnsureUserHasSubscription;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveApiCompany;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'subscribed' => EnsureUserHasSubscription::class,
            'admin' => EnsureUserIsAdmin::class,
            'api.company' => ResolveApiCompany::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        /**
         * The API is stateless, so nothing there should ever be answered with a
         * redirect to the login page. Forcing JSON on `api/*` means an expired
         * token comes back as a 401 the mobile client can act on, rather than a
         * 302 it would have to guess at.
         */
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
