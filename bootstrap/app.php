<?php

use App\Console\Commands\ApiSpecBuildCommand;
use App\Console\Commands\ApiSpecPostmanCommand;
use App\Console\Commands\ApiSpecSdkTypescriptCommand;
use App\Console\Commands\CleanupExpiredExportsCommand;
use App\Console\Commands\DemoReset;
use App\Http\Middleware\AdminGuard;
use App\Http\Middleware\ApiKeyAuth;
use App\Http\Middleware\EnsureAnalyticsAccess;
use App\Http\Middleware\ApplyCompanyLocale;
use App\Http\Middleware\EnsureRiskAccess;
use App\Http\Middleware\EnsureApprovalsAccess;
use App\Http\Middleware\EnsureRmaAccess;
use App\Http\Middleware\EnsureCreditNotesAccess;
use App\Http\Middleware\EnsureSearchAccess;
use App\Http\Middleware\EnsureDigitalTwinAccess;
use App\Http\Middleware\EnsurePrAccess;
use App\Http\Middleware\EnsureMoneyAccess;
use App\Http\Middleware\EnsureLocalizationAccess;
use App\Http\Middleware\EnsureInventoryAccess;
use App\Http\Middleware\EnsureExportAccess;
use App\Http\Middleware\RateLimitEnforcer;
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
        CleanupExpiredExportsCommand::class,
        ApiSpecBuildCommand::class,
        ApiSpecPostmanCommand::class,
        ApiSpecSdkTypescriptCommand::class,
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
            'ensure.pr.access' => EnsurePrAccess::class,
            'ensure.money.access' => EnsureMoneyAccess::class,
            'ensure.localization.access' => EnsureLocalizationAccess::class,
            'ensure.inventory.access' => EnsureInventoryAccess::class,
            'ensure.export.access' => EnsureExportAccess::class,
            'buyer_admin_only' => BuyerAdminOnly::class,
            'apply.company.locale' => ApplyCompanyLocale::class,
            'admin.guard' => AdminGuard::class,
            'api.key.auth' => ApiKeyAuth::class,
            'rate.limit.enforcer' => RateLimitEnforcer::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
