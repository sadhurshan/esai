<?php

use App\Console\Commands\ApiSpecBuildCommand;
use App\Console\Commands\ApiSpecPostmanCommand;
use App\Console\Commands\ApiSpecSdkTypescriptCommand;
use App\Console\Commands\CleanupExpiredExportsCommand;
use App\Console\Commands\DemoReset;
use App\Console\Commands\BackfillSupplierPersonas;
use App\Http\Middleware\AiRateLimiter;
use App\Http\Middleware\AdminGuard;
use App\Http\Middleware\AuthenticateApiSession;
use App\Http\Middleware\ApiKeyAuth;
use App\Http\Middleware\EnsureAnalyticsAccess;
use App\Http\Middleware\ApplyCompanyLocale;
use App\Http\Middleware\EnsureAiServiceAvailable;
use App\Http\Middleware\EnsureBuyerAccess;
use App\Http\Middleware\EnsureBillingAccess;
use App\Http\Middleware\EnsureRiskAccess;
use App\Http\Middleware\EnsureCompanyApproved;
use App\Http\Middleware\EnsureApprovalsAccess;
use App\Http\Middleware\EnsureRmaAccess;
use App\Http\Middleware\EnsureCreditNotesAccess;
use App\Http\Middleware\EnsureSearchAccess;
use App\Http\Middleware\EnsureNotificationAccess;
use App\Http\Middleware\EnsureEventAccess;
use App\Http\Middleware\EnsureDigitalTwinAccess;
use App\Http\Middleware\EnsurePrAccess;
use App\Http\Middleware\EnsureRfpAccess;
use App\Http\Middleware\EnsureMoneyAccess;
use App\Http\Middleware\EnsureOrdersAccess;
use App\Http\Middleware\EnsureSupplierInvoicingAccess;
use App\Http\Middleware\EnsureSourcingAccess;
use App\Http\Middleware\EnsureLocalizationAccess;
use App\Http\Middleware\EnsureInventoryAccess;
use App\Http\Middleware\EnsureExportAccess;
use App\Http\Middleware\ResolveCompanyContext;
use App\Http\Middleware\RateLimitEnforcer;
use App\Http\Middleware\BuyerAdminOnly;
use App\Http\Middleware\HandleAppearance;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Session\Middleware\StartSession;

$runtimeEnvironment = $_SERVER['APP_ENV']
    ?? $_ENV['APP_ENV']
    ?? getenv('APP_ENV')
    ?? null;

$app = Application::configure(basePath: dirname(__DIR__))
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
        BackfillSupplierPersonas::class,
    ])
    ->withBroadcasting(__DIR__.'/../routes/channels.php')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(
            append: [
                HandleAppearance::class,
                AddLinkHeadersForPreloadedAssets::class,
            ],
            replace: [
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class => \App\Http\Middleware\VerifyCsrfToken::class,
            ],
        );

        $middleware->alias([
            'ensure.subscribed' => \App\Http\Middleware\EnsureSubscribed::class,
            'ensure.company.registered' => \App\Http\Middleware\EnsureCompanyRegistered::class,
            'ensure.company.onboarded' => \App\Http\Middleware\EnsureCompanyOnboarded::class,
            'ensure.company.approved' => EnsureCompanyApproved::class,
            'ensure.supplier.approved' => \App\Http\Middleware\EnsureSupplierApproved::class,
            'ensure.analytics.access' => EnsureAnalyticsAccess::class,
            'ensure.risk.access' => EnsureRiskAccess::class,
            'ensure.approvals.access' => EnsureApprovalsAccess::class,
            'ensure.rma.access' => EnsureRmaAccess::class,
            'ensure.credit_notes.access' => EnsureCreditNotesAccess::class,
            'ensure.search.access' => EnsureSearchAccess::class,
            'ensure.notifications.access' => EnsureNotificationAccess::class,
            'ensure.events.access' => EnsureEventAccess::class,
            'ensure.digital_twin.access' => EnsureDigitalTwinAccess::class,
            'ensure.pr.access' => EnsurePrAccess::class,
            'ensure.money.access' => EnsureMoneyAccess::class,
            'ensure.localization.access' => EnsureLocalizationAccess::class,
            'ensure.inventory.access' => EnsureInventoryAccess::class,
            'ensure.export.access' => EnsureExportAccess::class,
            'buyer_access' => EnsureBuyerAccess::class,
            'billing_access' => EnsureBillingAccess::class,
            'orders_access' => EnsureOrdersAccess::class,
            'sourcing_access' => EnsureSourcingAccess::class,
            'rfp_access' => EnsureRfpAccess::class,
            'supplier_invoicing_access' => EnsureSupplierInvoicingAccess::class,
            'buyer_admin_only' => BuyerAdminOnly::class,
            'apply.company.locale' => ApplyCompanyLocale::class,
            'admin.guard' => AdminGuard::class,
            'bypass.company.context' => \App\Http\Middleware\BypassCompanyContext::class,
            'api.key.auth' => ApiKeyAuth::class,
            'rate.limit.enforcer' => RateLimitEnforcer::class,
            'ai.rate.limit' => \App\Http\Middleware\AiRateLimiter::class,
            'ai.ensure.available' => \App\Http\Middleware\EnsureAiServiceAvailable::class,
            'auth.session' => AuthenticateApiSession::class,
        ]);

        $middleware->prependToGroup('api', AuthenticateApiSession::class);
        $middleware->appendToGroup('api', StartSession::class);
        $middleware->appendToGroup('api', ResolveCompanyContext::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            $errors = $exception->errors();
            $firstError = collect($errors)->flatten()->first();

            $message = $exception->getMessage();
            if ($firstError !== null && ($message === '' || $message === 'The given data was invalid.')) {
                $message = $firstError;
            }

            $requestId = (string) ($request->headers->get('X-Request-Id')
                ?? $request->attributes->get('request_id')
                ?? Str::uuid());

            $request->attributes->set('request_id', $requestId);

            $payload = [
                'status' => 'error',
                'message' => $message,
                'data' => null,
                'meta' => ['request_id' => $requestId],
            ];

            if ($errors !== []) {
                $payload['errors'] = $errors;
            }

            return response()->json($payload, $exception->status);
        });
    })->create();

if ($runtimeEnvironment === 'testing') {
    // Force the testing environment to use the isolated .env.testing database config.
    $app->loadEnvironmentFrom('.env.testing');
}

return $app;
