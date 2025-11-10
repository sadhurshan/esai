<?php

use App\Console\Commands\DemoReset;
use App\Http\Middleware\EnsureAnalyticsAccess;
use App\Http\Middleware\EnsureRiskAccess;
use App\Http\Middleware\EnsureApprovalsAccess;
use App\Http\Middleware\EnsureRmaAccess;
use App\Http\Middleware\EnsureCreditNotesAccess;
use App\Http\Middleware\EnsureSearchAccess;
use App\Http\Middleware\EnsureDigitalTwinAccess;
use App\Http\Middleware\BuyerAdminOnly;
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
            'ensure.company.registered' => \App\Http\Middleware\EnsureCompanyRegistered::class,
            'ensure.company.onboarded' => \App\Http\Middleware\EnsureCompanyOnboarded::class,
            'ensure.supplier.approved' => \App\Http\Middleware\EnsureSupplierApproved::class,
            'ensure.analytics.access' => EnsureAnalyticsAccess::class,
            'ensure.risk.access' => EnsureRiskAccess::class,
            'ensure.approvals.access' => EnsureApprovalsAccess::class,
            'ensure.rma.access' => EnsureRmaAccess::class,
            'ensure.credit_notes.access' => EnsureCreditNotesAccess::class,
            'ensure.search.access' => EnsureSearchAccess::class,
            'ensure.digital_twin.access' => EnsureDigitalTwinAccess::class,
            'buyer_admin_only' => BuyerAdminOnly::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
