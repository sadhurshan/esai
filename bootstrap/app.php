<?php

use App\Console\Commands\DemoReset;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        DemoReset::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(
            append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            ],
            replace: [
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class => \App\Http\Middleware\VerifyCsrfToken::class,
                \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class => \App\Http\Middleware\VerifyCsrfToken::class,
            ],
        );

        $middleware->alias([
            'ensure.subscribed' => \App\Http\Middleware\EnsureSubscribed::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
