<?php

use App\Http\Controllers\Admin\AdminAnalyticsController;
use App\Http\Controllers\Admin\ApiKeyController as AdminApiKeyController;
use App\Http\Controllers\Admin\AiEventController as AdminAiEventController;
use App\Http\Controllers\Admin\AiModelMetricController as AdminAiModelMetricController;
use App\Http\Controllers\Admin\AiTrainingController as AdminAiTrainingController;
use App\Http\Controllers\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Admin\CompanyFeatureFlagController as AdminCompanyFeatureFlagController;
use App\Http\Controllers\Admin\DigitalTwinCategoryController as AdminDigitalTwinCategoryController;
use App\Http\Controllers\Admin\DigitalTwinController as AdminDigitalTwinController;
use App\Http\Controllers\Admin\DigitalTwinAssetController as AdminDigitalTwinAssetController;
use App\Http\Controllers\Admin\DigitalTwinAuditEventController as AdminDigitalTwinAuditEventController;
use App\Http\Controllers\Admin\EmailTemplateController as AdminEmailTemplateController;
use App\Http\Controllers\Admin\HealthController as AdminHealthController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\RoleTemplateController as AdminRoleTemplateController;
use App\Http\Controllers\Admin\RateLimitController as AdminRateLimitController;
use App\Http\Controllers\Admin\SupplierScrapeController as AdminSupplierScrapeController;
use App\Http\Controllers\Admin\WebhookDeliveryController as AdminWebhookDeliveryController;
use App\Http\Controllers\Admin\WebhookSubscriptionController as AdminWebhookSubscriptionController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AwardController;
use App\Http\Controllers\Api\AwardLineController;
use App\Http\Controllers\Api\Billing\PlanCheckoutController;
use App\Http\Controllers\Api\Billing\BillingPortalController;
use App\Http\Controllers\Api\Billing\BillingInvoiceController;
use App\Http\Controllers\Api\Billing\StripeWebhookController;
use App\Http\Controllers\Api\CopilotController;
use App\Http\Controllers\Api\SupplierRiskController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EventDeliveryController;
use App\Http\Controllers\Api\SupplierEsgController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\DocsController;
use App\Http\Controllers\Api\MoneySettingsController;
use App\Http\Controllers\Api\FxRateController;
use App\Http\Controllers\Api\Localization\LocaleSettingsController;
use App\Http\Controllers\Api\Settings\CompanySettingsController;
use App\Http\Controllers\Api\Settings\NumberingSettingsController;
use App\Http\Controllers\Api\Settings\CompanyAiSettingsController;
use App\Http\Controllers\Api\Localization\UomController;
use App\Http\Controllers\Api\Localization\UomConversionController;
use App\Http\Controllers\Api\CompanyDocumentController;
use App\Http\Controllers\Api\CompanyMemberController;
use App\Http\Controllers\Api\CompanyRoleTemplateController;
use App\Http\Controllers\Api\CompanyInvitationController;
use App\Http\Controllers\Api\CompanyRegistrationController;
use App\Http\Controllers\Api\GoodsReceiptNoteController;
use App\Http\Controllers\Api\Inventory\InventoryItemController;
use App\Http\Controllers\Api\Inventory\InventoryLocationController;
use App\Http\Controllers\Api\Inventory\InventoryLowStockController;
use App\Http\Controllers\Api\Inventory\InventoryMovementController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\TaxCodeController;
use App\Http\Controllers\Api\Orders\BuyerOrderController;
use App\Http\Controllers\Api\Orders\SupplierOrderController;
use App\Http\Controllers\Api\Orders\SupplierShipmentController;
use App\Http\Controllers\Api\Supplier\SupplierInvoiceController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\RFQController;
use App\Http\Controllers\Api\RFQQuoteController;
use App\Http\Controllers\Api\PoChangeOrderController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\QuoteInboxController;
use App\Http\Controllers\Api\QuoteLineController;
use App\Http\Controllers\Api\QuoteShortlistController;
use App\Http\Controllers\Api\RfqClarificationController;
use App\Http\Controllers\Api\RfqAwardCandidateController;
use App\Http\Controllers\Api\QuoteRevisionController;
use App\Http\Controllers\Api\RfqAwardController;
use App\Http\Controllers\Api\RfqLineController;
use App\Http\Controllers\Api\RfqTimelineController;
use App\Http\Controllers\Api\RfqAttachmentController;
use App\Http\Controllers\Api\RfqInvitationController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\PurchaseOrderShipmentController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierQuoteController;
use App\Http\Controllers\Api\Ai\AiDocumentIndexController;
use App\Http\Controllers\Api\Ai\CopilotSearchController;
use App\Http\Controllers\Api\V1\AiActionsController;
use App\Http\Controllers\Api\V1\AiChatController;
use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\AiWorkflowController;
use App\Http\Controllers\Api\SupplierRfqInboxController;
use App\Http\Controllers\Api\SupplierDashboardController;
use App\Http\Controllers\Api\SupplierApplicationController;
use App\Http\Controllers\Api\SupplierDocumentController;
use App\Http\Controllers\Api\SupplierSelfServiceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\NcrController;
use App\Http\Controllers\Api\SavedSearchController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\UserCompanyController;
use App\Http\Controllers\Api\Admin\CompanyApprovalController;
use App\Http\Controllers\Api\Admin\SupplierApplicationReviewController;
use App\Http\Controllers\Api\ApprovalRuleController;
use App\Http\Controllers\Api\ApprovalRequestController;
use App\Http\Controllers\Api\DelegationController;
use App\Http\Controllers\Api\RmaController;
use App\Http\Controllers\Api\CreditNoteController;
use App\Http\Controllers\Api\QuoteTotalsController;
use App\Http\Controllers\Api\PoTotalsController;
use App\Http\Controllers\Api\InvoiceTotalsController;
use App\Http\Controllers\Api\CreditTotalsController;
use App\Http\Controllers\Api\RfpController;
use App\Http\Controllers\Api\RfpProposalController;
use App\Http\Controllers\Api\DownloadJobController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\CompanyPlanController;
use App\Http\Controllers\Api\PlanCatalogController;
use App\Http\Controllers\Api\Library\DigitalTwinController as LibraryDigitalTwinController;
use App\Http\Controllers\Api\DigitalTwin\AssetBomController as DigitalTwinAssetBomController;
use App\Http\Controllers\Api\DigitalTwin\AssetController as DigitalTwinAssetController;
use App\Http\Controllers\Api\DigitalTwin\AssetMaintenanceController as DigitalTwinAssetMaintenanceController;
use App\Http\Controllers\Api\DigitalTwin\LocationController as DigitalTwinLocationController;
use App\Http\Controllers\Api\DigitalTwin\MaintenanceProcedureController as DigitalTwinMaintenanceProcedureController;
use App\Http\Controllers\Api\DigitalTwin\SystemController as DigitalTwinSystemController;
use Illuminate\Support\Facades\Route;

Route::get('health', [HealthController::class, '__invoke']);
Route::get('docs/openapi.json', [DocsController::class, 'openApi']);
Route::get('docs/postman.json', [DocsController::class, 'postman']);
Route::get('plans', [PlanCatalogController::class, 'index']);
Route::post('company-invitations/{token}/accept', [CompanyInvitationController::class, 'accept'])
    ->withoutMiddleware([
        'auth',
        \Illuminate\Auth\Middleware\Authenticate::class,
        \App\Http\Middleware\AuthenticateApiSession::class,
    ]);

Route::middleware(['auth'])->group(function (): void {
    Route::post('company/plan-selection', [CompanyPlanController::class, 'store']);
    Route::post('billing/checkout', [PlanCheckoutController::class, 'store']);
    Route::post('billing/portal', [BillingPortalController::class, 'store']);
    Route::get('billing/invoices', [BillingInvoiceController::class, 'index'])
        ->middleware(['ensure.company.onboarded', 'billing_access:read']);
});

Route::middleware(['auth', 'ensure.company.approved'])->prefix('me/supplier-documents')->group(function (): void {
    Route::get('/', [SupplierDocumentController::class, 'index']);
    Route::post('/', [SupplierDocumentController::class, 'store']);
    Route::delete('{document}', [SupplierDocumentController::class, 'destroy']);
});

Route::prefix('me')->group(function (): void {
    Route::get('profile', [UserProfileController::class, 'show']);
    Route::patch('profile', [UserProfileController::class, 'update']);
    Route::get('companies', [UserCompanyController::class, 'index']);
    Route::post('companies/switch', [UserCompanyController::class, 'switch']);
});

Route::middleware(['auth'])->prefix('me')->group(function (): void {
    Route::get('supplier-application/status', [SupplierSelfServiceController::class, 'status']);
    Route::put('supplier/visibility', [SupplierSelfServiceController::class, 'updateVisibility']);
    Route::post('apply-supplier', [SupplierApplicationController::class, 'selfApply']);
});

Route::middleware(['auth', 'admin.guard', \App\Http\Middleware\BypassCompanyContext::class])->prefix('admin')->group(function (): void {
    Route::apiResource('plans', AdminPlanController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::post('companies/{company}/assign-plan', [AdminCompanyController::class, 'assignPlan']);
    Route::put('companies/{company}/status', [AdminCompanyController::class, 'updateStatus']);
    Route::get('companies/{company}/feature-flags', [AdminCompanyFeatureFlagController::class, 'index']);
    Route::post('companies/{company}/feature-flags', [AdminCompanyFeatureFlagController::class, 'store']);
    Route::put('companies/{company}/feature-flags/{flag}', [AdminCompanyFeatureFlagController::class, 'update']);
    Route::delete('companies/{company}/feature-flags/{flag}', [AdminCompanyFeatureFlagController::class, 'destroy']);
    Route::get('companies', [CompanyApprovalController::class, 'index']);
    Route::post('companies/{company}/approve', [CompanyApprovalController::class, 'approve']);
    Route::post('companies/{company}/reject', [CompanyApprovalController::class, 'reject']);
    Route::apiResource('email-templates', AdminEmailTemplateController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::post('email-templates/{email_template}/preview', [AdminEmailTemplateController::class, 'preview']);
    Route::apiResource('digital-twin-categories', AdminDigitalTwinCategoryController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::apiResource('digital-twins', AdminDigitalTwinController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::post('digital-twins/{digital_twin}/publish', [AdminDigitalTwinController::class, 'publish']);
    Route::post('digital-twins/{digital_twin}/archive', [AdminDigitalTwinController::class, 'archive']);
    Route::post('digital-twins/{digital_twin}/assets', [AdminDigitalTwinAssetController::class, 'store']);
    Route::delete('digital-twins/{digital_twin}/assets/{asset}', [AdminDigitalTwinAssetController::class, 'destroy']);
    Route::get('digital-twins/{digital_twin}/audit-events', [AdminDigitalTwinAuditEventController::class, 'index']);
    Route::get('api-keys', [AdminApiKeyController::class, 'index']);
    Route::post('api-keys', [AdminApiKeyController::class, 'store']);
    Route::post('api-keys/{key}/rotate', [AdminApiKeyController::class, 'rotate']);
    Route::post('api-keys/{key}/toggle', [AdminApiKeyController::class, 'toggle']);
    Route::delete('api-keys/{key}', [AdminApiKeyController::class, 'destroy']);
    Route::apiResource('rate-limits', AdminRateLimitController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::apiResource('webhook-subscriptions', AdminWebhookSubscriptionController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::post('webhook-subscriptions/{webhook_subscription}/test', [AdminWebhookSubscriptionController::class, 'test']);
    Route::get('webhook-deliveries', [AdminWebhookDeliveryController::class, 'index']);
    Route::post('webhook-deliveries/{delivery}/retry', [AdminWebhookDeliveryController::class, 'retry']);
    Route::get('health', [AdminHealthController::class, 'show']);
    Route::get('roles', [AdminRoleTemplateController::class, 'index']);
    Route::patch('roles/{roleTemplate}', [AdminRoleTemplateController::class, 'update']);
    Route::get('audit', [AdminAuditLogController::class, 'index']);
    Route::get('ai-events', [AdminAiEventController::class, 'index']);
    Route::get('ai-model-metrics', [AdminAiModelMetricController::class, 'index']);
    Route::get('company-approvals', [CompanyApprovalController::class, 'index']);
    Route::post('company-approvals/{company}/approve', [CompanyApprovalController::class, 'approve']);
    Route::post('company-approvals/{company}/reject', [CompanyApprovalController::class, 'reject']);
    Route::get('analytics/overview', [AdminAnalyticsController::class, 'overview']);

    Route::prefix('supplier-applications')->group(function (): void {
        Route::get('/', [SupplierApplicationReviewController::class, 'index']);
        Route::post('{application}/approve', [SupplierApplicationReviewController::class, 'approve']);
        Route::post('{application}/reject', [SupplierApplicationReviewController::class, 'reject']);
        Route::get('{application}/audit-logs', [SupplierApplicationReviewController::class, 'auditLogs']);
    });
});

Route::prefix('files')->middleware(['auth'])->group(function (): void {
    Route::get('cad/{rfq}', [FileController::class, 'cad']);
    Route::get('attachments/{quote}', [FileController::class, 'attachment']);
});

Route::prefix('suppliers')->group(function (): void {
    Route::get('/', [SupplierController::class, 'index']);
    Route::get('{supplier}', [SupplierController::class, 'show']);

    Route::prefix('{supplier}/esg')
        ->middleware(['ensure.company.onboarded', 'ensure.subscribed', 'ensure.risk.access'])
        ->group(function (): void {
            Route::get('/', [SupplierEsgController::class, 'index']);
            Route::post('/', [SupplierEsgController::class, 'store']);
            Route::post('export', [SupplierEsgController::class, 'export']);
            Route::put('{record}', [SupplierEsgController::class, 'update']);
            Route::delete('{record}', [SupplierEsgController::class, 'destroy']);
        });
});

Route::middleware(['auth'])->prefix('supplier-applications')->group(function (): void {
    Route::get('/', [SupplierApplicationController::class, 'index']);
    Route::post('/', [SupplierApplicationController::class, 'store']);
    Route::get('{application}', [SupplierApplicationController::class, 'show']);
    Route::delete('{application}', [SupplierApplicationController::class, 'destroy']);
});

Route::prefix('ai')
    ->middleware([
        'auth',
        'ensure.company.onboarded:strict',
        'ensure.company.approved',
        'ensure.subscribed',
        'ai.ensure.available',
        'ai.rate.limit',
    ])
    ->group(function (): void {
        Route::post('forecast', [AiController::class, 'forecast'])
            ->middleware(['ensure.inventory.access']);

        Route::post('supplier-risk', [AiController::class, 'supplierRisk'])
            ->middleware(['ensure.risk.access']);
    });

Route::prefix('v1')->group(function (): void {
    Route::middleware([
        'auth',
        'ensure.company.onboarded:strict',
        'ensure.company.approved',
        'ensure.subscribed',
        'buyer_admin_only',
    ])->prefix('admin/ai')->group(function (): void {
        Route::post('reindex-document', [AiDocumentIndexController::class, 'reindex']);
    });

    $copilotActionMiddleware = [
        'auth',
        'ensure.company.onboarded:strict',
        'ensure.company.approved',
        'ensure.subscribed',
        'buyer_access',
    ];

    $copilotChatMiddleware = array_merge($copilotActionMiddleware, ['ai.ensure.available', 'ai.rate.limit']);

    Route::prefix('ai/actions')->group(function () use ($copilotActionMiddleware): void {
        Route::post('plan', [AiActionsController::class, 'plan'])
            ->middleware(array_merge($copilotActionMiddleware, ['ai.ensure.available', 'ai.rate.limit']));

        Route::post('{draft}/approve', [AiActionsController::class, 'approve'])
            ->middleware($copilotActionMiddleware);

        Route::post('{draft}/reject', [AiActionsController::class, 'reject'])
            ->middleware($copilotActionMiddleware);

        Route::post('{draft}/feedback', [AiActionsController::class, 'feedback'])
            ->middleware($copilotActionMiddleware);
    });

    Route::prefix('ai/drafts')
        ->middleware($copilotActionMiddleware)
        ->group(function (): void {
            Route::post('{draft}/approve', [AiActionsController::class, 'approve']);
            Route::post('{draft}/reject', [AiActionsController::class, 'reject']);
        });

    Route::prefix('ai/chat')
        ->middleware($copilotChatMiddleware)
        ->group(function (): void {
            Route::get('threads', [AiChatController::class, 'index']);
            Route::post('threads', [AiChatController::class, 'store']);
            Route::get('threads/{thread}', [AiChatController::class, 'show']);
            Route::get('threads/{thread}/stream', [AiChatController::class, 'stream']);
            Route::post('threads/{thread}/send', [AiChatController::class, 'send']);
            Route::post('threads/{thread}/tools/resolve', [AiChatController::class, 'resolveTools']);
        });

    Route::prefix('ai/workflows')->group(function () use ($copilotActionMiddleware): void {
        $workflowMiddleware = array_merge($copilotActionMiddleware, ['ensure.ai.workflows.access', 'ai.ensure.available', 'ai.rate.limit']);

        Route::get('/', [AiWorkflowController::class, 'index'])
            ->middleware($workflowMiddleware);

        Route::post('start', [AiWorkflowController::class, 'start'])
            ->middleware($workflowMiddleware);

        Route::get('{workflow}/next', [AiWorkflowController::class, 'next'])
            ->middleware($workflowMiddleware);

        Route::get('{workflow}/events', [AiWorkflowController::class, 'events'])
            ->middleware($workflowMiddleware);

        Route::post('{workflow}/complete', [AiWorkflowController::class, 'complete'])
            ->middleware($workflowMiddleware);
    });

    Route::middleware([
        'auth',
        'can:canTrainAi',
        'admin.guard:super',
        \App\Http\Middleware\BypassCompanyContext::class,
        'ensure.ai.training.enabled',
    ])->prefix('admin/ai-training')->group(function (): void {
        Route::get('jobs', [AdminAiTrainingController::class, 'index']);
        Route::post('start', [AdminAiTrainingController::class, 'start']);
        Route::get('jobs/{model_training_job}', [AdminAiTrainingController::class, 'show']);
        Route::post('jobs/{model_training_job}/refresh', [AdminAiTrainingController::class, 'refresh']);
    });

    Route::middleware([
        'auth',
        'admin.guard:super',
        \App\Http\Middleware\BypassCompanyContext::class,
    ])->prefix('admin')->group(function (): void {
        Route::get('supplier-scrapes', [AdminSupplierScrapeController::class, 'index']);
        Route::post('supplier-scrapes/start', [AdminSupplierScrapeController::class, 'start']);
        Route::get('supplier-scrapes/{supplier_scrape_job}/results', [AdminSupplierScrapeController::class, 'results']);
        Route::post('scraped-suppliers/{scraped_supplier}/approve', [AdminSupplierScrapeController::class, 'approve']);
        Route::delete('scraped-suppliers/{scraped_supplier}', [AdminSupplierScrapeController::class, 'discard']);
    });
});

Route::middleware(['auth', 'ensure.subscribed', 'ensure.digital_twin.access', 'buyer_access'])
    ->prefix('library')
    ->group(function (): void {
        Route::get('digital-twins', [LibraryDigitalTwinController::class, 'index']);
        Route::get('digital-twins/{digital_twin}', [LibraryDigitalTwinController::class, 'show']);
        Route::post('digital-twins/{digital_twin}/use-for-rfq', [LibraryDigitalTwinController::class, 'useForRfq']);
    });


Route::prefix('rfps')->group(function (): void {
    Route::get('/', [RfpController::class, 'index'])
        ->middleware('rfp_access:read');
    Route::post('/', [RfpController::class, 'store'])
        ->middleware(['ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'rfp_access:write']);
    Route::get('{rfp}', [RfpController::class, 'show'])
        ->middleware('rfp_access:read');
    Route::put('{rfp}', [RfpController::class, 'update'])
        ->middleware(['ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'rfp_access:write']);
    Route::post('{rfp}/publish', [RfpController::class, 'publish'])
        ->middleware(['ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'rfp_access:write']);
    Route::post('{rfp}/move-to-review', [RfpController::class, 'moveToReview'])
        ->middleware(['ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'rfp_access:write']);
    Route::post('{rfp}/award', [RfpController::class, 'award'])
        ->middleware(['ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'rfp_access:write']);
    Route::post('{rfp}/close-no-award', [RfpController::class, 'closeWithoutAward'])
        ->middleware(['ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'rfp_access:write']);
    Route::get('{rfp}/proposals', [RfpProposalController::class, 'index'])
        ->middleware('rfp_access:read');
    Route::post('{rfp}/proposals', [RfpProposalController::class, 'store'])
        ->middleware(['ensure.subscribed', 'ensure.supplier.approved']);
});


Route::prefix('rfqs')->group(function (): void {
    Route::get('/', [RFQController::class, 'index'])
        ->middleware('sourcing_access:read');
    Route::post('/', [RFQController::class, 'store'])
        ->middleware(['ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'sourcing_access:write']);
    Route::get('{rfq}', [RFQController::class, 'show'])
        ->middleware('sourcing_access:read');
    Route::get('{rfq}/lines', [RfqLineController::class, 'index'])
        ->middleware('sourcing_access:read');
    Route::post('{rfq}/lines', [RfqLineController::class, 'store'])
        ->middleware(['ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'sourcing_access:write']);
    Route::put('{rfq}/lines/{line}', [RfqLineController::class, 'update'])
        ->middleware(['ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'sourcing_access:write']);
    Route::delete('{rfq}/lines/{line}', [RfqLineController::class, 'destroy'])
        ->middleware(['ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'sourcing_access:write']);
    Route::get('{rfq}/attachments', [RfqAttachmentController::class, 'index'])
        ->middleware('sourcing_access:read');
    Route::post('{rfq}/attachments', [RfqAttachmentController::class, 'store'])
        ->middleware(['ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'sourcing_access:write']);
    Route::post('{rfq}/publish', [RFQController::class, 'publish'])
        ->middleware(['ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'sourcing_access:write']);
    Route::post('{rfq}/cancel', [RFQController::class, 'cancel'])
        ->middleware(['ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'sourcing_access:write']);
    Route::post('{rfq}/close', [RFQController::class, 'close'])
        ->middleware(['ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'sourcing_access:write']);
    Route::get('{rfq}/timeline', RfqTimelineController::class)
        ->middleware('sourcing_access:read');
    Route::put('{rfq}', [RFQController::class, 'update'])
        ->middleware(['ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'sourcing_access:write']);
    Route::delete('{rfq}', [RFQController::class, 'destroy'])
        ->middleware(['ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'sourcing_access:write']);

    Route::get('{rfq}/invitations', [RfqInvitationController::class, 'index'])
        ->middleware('sourcing_access:read');
    Route::post('{rfq}/invitations', [RfqInvitationController::class, 'store'])
        ->middleware(['ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'sourcing_access:write']);

    Route::get('{rfq}/quotes', [QuoteController::class, 'index'])
        ->middleware('sourcing_access:read');

    Route::get('{rfq}/quotes/compare', [QuoteController::class, 'compare'])
        ->middleware('sourcing_access:read');

    Route::get('{rfq}/award-candidates', [RfqAwardCandidateController::class, 'index'])
        ->middleware(['ensure.company.onboarded', 'ensure.subscribed', 'sourcing_access:read']);

    Route::prefix('{rfq}/quotes/{quote}')
        ->middleware(['ensure.company.onboarded', 'ensure.subscribed', 'ensure.supplier.approved'])
        ->group(function (): void {
            Route::get('/revisions', [QuoteRevisionController::class, 'index'])
                ->withoutMiddleware('ensure.supplier.approved');
            Route::post('/revisions', [QuoteRevisionController::class, 'store']);
        });

    Route::post('{rfq}/award', [AwardController::class, 'store'])
        ->middleware(['ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'sourcing_access:write']);
    Route::post('{rfq}/award-lines', [RfqAwardController::class, 'awardLines'])
        ->middleware(['ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'sourcing_access:write']);
    Route::post('{rfq}/extend-deadline', [RFQController::class, 'extendDeadline'])
        ->middleware(['ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'sourcing_access:write']);

    Route::prefix('{rfq}/clarifications')
        ->middleware(['ensure.company.onboarded:strict', 'ensure.company.approved', 'ensure.subscribed', 'sourcing_access:read'])
        ->group(function (): void {
            Route::get('/', [RfqClarificationController::class, 'index']);
            Route::get('/{clarification}/attachments/{attachment}', [RfqClarificationController::class, 'downloadAttachment'])
                ->name('rfqs.clarifications.attachments.download');
            Route::post('/question', [RfqClarificationController::class, 'storeQuestion']);
            Route::post('/answer', [RfqClarificationController::class, 'storeAnswer']);
            Route::post('/amendment', [RfqClarificationController::class, 'storeAmendment'])
                ->middleware('sourcing_access:write');
        });
});

Route::prefix('rfq-quotes')->middleware(['auth'])->group(function (): void {
    Route::get('{rfq}', [RFQQuoteController::class, 'index']);
    Route::post('{rfq}', [RFQQuoteController::class, 'store'])
        ->middleware('ensure.supplier.approved');
});

Route::prefix('awards')
    ->middleware(['ensure.company.onboarded', 'ensure.subscribed'])
    ->group(function (): void {
        Route::post('/', [AwardLineController::class, 'store']);
        Route::delete('{award}', [AwardLineController::class, 'destroy']);
    });

Route::post('quotes', [QuoteController::class, 'storeStandalone'])
    ->middleware(['ensure.subscribed', 'ensure.supplier.approved']);

Route::post('quotes/{quote}/submit', [QuoteController::class, 'submitStandalone'])
    ->middleware(['ensure.subscribed', 'ensure.supplier.approved']);

Route::middleware([
    'auth',
    'ensure.company.onboarded:strict',
    'ensure.company.approved',
    'ensure.subscribed',
    'sourcing_access:write',
])->prefix('quotes')->group(function (): void {
    Route::post('{quote}/shortlist', [QuoteShortlistController::class, 'store']);
    Route::delete('{quote}/shortlist', [QuoteShortlistController::class, 'destroy']);
});

Route::prefix('rfqs/{rfq}/quotes')->group(function (): void {
    Route::post('/', [QuoteController::class, 'store'])
        ->middleware(['ensure.subscribed', 'ensure.supplier.approved']);

    Route::put('{quote}', [QuoteController::class, 'submit'])
        ->middleware(['ensure.subscribed', 'ensure.supplier.approved']);

    Route::patch('{quote}/draft', [QuoteController::class, 'updateDraft'])
        ->middleware(['ensure.subscribed', 'ensure.supplier.approved']);

    Route::patch('{quote}', [QuoteController::class, 'withdraw'])
        ->middleware(['ensure.subscribed', 'ensure.supplier.approved']);

    Route::post('{quote}/withdraw', [QuoteController::class, 'withdraw'])
        ->middleware(['ensure.supplier.approved']);
});

Route::prefix('pos')
    ->middleware(['ensure.company.onboarded', 'ensure.subscribed', 'orders_access:write'])
    ->group(function (): void {
        Route::post('from-awards', [PurchaseOrderController::class, 'createFromAwards']);
    });

Route::middleware(['auth', 'ensure.company.onboarded', 'ensure.subscribed', 'orders_access:read'])
    ->prefix('buyer/orders')
    ->group(function (): void {
        Route::get('/', [BuyerOrderController::class, 'index']);
        Route::get('{order}', [BuyerOrderController::class, 'show']);
    });

Route::middleware(['auth', 'ensure.supplier.approved'])
    ->prefix('supplier/dashboard')
    ->group(function (): void {
        Route::get('metrics', [SupplierDashboardController::class, 'metrics']);
    });

Route::middleware(['auth', 'ensure.supplier.approved', 'orders_access:read'])
    ->prefix('supplier/orders')
    ->group(function (): void {
        Route::get('/', [SupplierOrderController::class, 'index']);
        Route::get('{order}', [SupplierOrderController::class, 'show']);
        Route::post('{order}/ack', [SupplierOrderController::class, 'acknowledge'])
            ->middleware('orders_access:write');
        Route::post('{order}/shipments', [SupplierShipmentController::class, 'store'])
            ->middleware('orders_access:write');
    });

Route::middleware(['auth', 'ensure.supplier.approved', 'orders_access:read', 'supplier_invoicing_access'])
    ->prefix('supplier')
    ->group(function (): void {
        Route::get('invoices', [SupplierInvoiceController::class, 'index']);
        Route::get('invoices/{invoice}', [SupplierInvoiceController::class, 'show']);
        Route::put('invoices/{invoice}', [SupplierInvoiceController::class, 'update'])
            ->middleware('supplier_invoicing_access:write');
        Route::post('invoices/{invoice}/submit', [SupplierInvoiceController::class, 'submit'])
            ->middleware('supplier_invoicing_access:write');

        Route::post('purchase-orders/{purchaseOrder}/invoices', [SupplierInvoiceController::class, 'store'])
            ->middleware(['orders_access:write', 'supplier_invoicing_access:write']);
    });

Route::middleware(['auth', 'ensure.supplier.approved', 'orders_access:write'])
    ->prefix('supplier/shipments')
    ->group(function (): void {
        Route::post('{shipment}/status', [SupplierShipmentController::class, 'updateStatus']);
    });

Route::get('quotes', [QuoteInboxController::class, 'index'])->middleware('auth');
Route::get('quotes/{quote}', [QuoteController::class, 'show']);
Route::prefix('quotes/{quote}')
    ->middleware(['ensure.supplier.approved'])
    ->group(function (): void {
        Route::post('lines', [QuoteLineController::class, 'store']);
        Route::put('lines/{quoteItem}', [QuoteLineController::class, 'update']);
        Route::delete('lines/{quoteItem}', [QuoteLineController::class, 'destroy']);
    });
Route::get('supplier/quotes', [SupplierQuoteController::class, 'index'])->middleware(['ensure.supplier.approved']);
Route::get('supplier/rfqs', [SupplierRfqInboxController::class, 'index'])->middleware(['auth', 'ensure.supplier.approved']);

Route::middleware('web')->group(function (): void {
    Route::prefix('purchase-orders')->middleware('auth')->group(function (): void {
        Route::get('/', [PurchaseOrderController::class, 'index'])
            ->middleware('orders_access:read');
        Route::post('{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])
            ->middleware(['ensure.company.onboarded', 'ensure.company.approved', 'ensure.subscribed', 'orders_access:write']);
        Route::post('{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
            ->middleware(['ensure.company.onboarded', 'ensure.company.approved', 'ensure.subscribed', 'orders_access:write']);
        Route::post('{purchaseOrder}/export', [PurchaseOrderController::class, 'export'])
            ->middleware(['ensure.company.onboarded', 'ensure.company.approved', 'ensure.subscribed', 'orders_access:write']);
        Route::post('{purchaseOrder}/acknowledge', [PurchaseOrderController::class, 'acknowledge'])
            ->middleware(['ensure.supplier.approved', 'orders_access:read']);
        Route::get('{purchaseOrder}/change-orders', [PoChangeOrderController::class, 'index']);
        Route::post('{purchaseOrder}/change-orders', [PoChangeOrderController::class, 'store'])
            ->middleware(['ensure.company.onboarded', 'ensure.company.approved', 'ensure.subscribed', 'orders_access:write']);
        Route::get('{purchaseOrder}/invoices', [InvoiceController::class, 'index'])
            ->middleware(['ensure.company.onboarded', 'billing_access:read']);
        Route::post('{purchaseOrder}/invoices', [InvoiceController::class, 'store'])
            ->middleware(['ensure.company.onboarded', 'ensure.subscribed', 'billing_access:write']);
        Route::prefix('{purchaseOrder}/grns')
            ->middleware(['ensure.company.onboarded', 'ensure.inventory.access'])
            ->group(function (): void {
                Route::get('/', [GoodsReceiptNoteController::class, 'index']);
                Route::get('{note}', [GoodsReceiptNoteController::class, 'show']);
                Route::post('/', [GoodsReceiptNoteController::class, 'store'])->middleware('ensure.subscribed');
                Route::put('{note}', [GoodsReceiptNoteController::class, 'update'])->middleware('ensure.subscribed');
                Route::delete('{note}', [GoodsReceiptNoteController::class, 'destroy'])->middleware('ensure.subscribed');
            });
        Route::get('{purchaseOrder}/documents/{document}/download', [PurchaseOrderController::class, 'downloadPdf'])
            ->middleware(['signed', 'orders_access:read'])
            ->name('purchase-orders.pdf.download');
        Route::get('{purchaseOrder}/events', [PurchaseOrderController::class, 'events'])
            ->middleware('orders_access:read');
        Route::get('{purchaseOrder}/shipments', [PurchaseOrderShipmentController::class, 'index'])
            ->middleware(['ensure.company.onboarded', 'orders_access:read']);
        Route::get('{purchaseOrder}', [PurchaseOrderController::class, 'show'])
            ->middleware('orders_access:read');
    });

    Route::prefix('receiving/grns')
        ->middleware(['ensure.company.onboarded', 'ensure.inventory.access'])
        ->group(function (): void {
            Route::get('/', [GoodsReceiptNoteController::class, 'companyIndex']);
            Route::get('{note}', [GoodsReceiptNoteController::class, 'companyShow']);
            Route::post('/', [GoodsReceiptNoteController::class, 'companyStore'])->middleware('ensure.subscribed');
            Route::post('{note}/attachments', [GoodsReceiptNoteController::class, 'companyAttachFile'])
                ->middleware('ensure.subscribed');
            Route::post('{note}/ncrs', [NcrController::class, 'store'])->middleware('ensure.subscribed');
        });

    Route::patch('receiving/ncrs/{ncr}', [NcrController::class, 'update'])
        ->middleware(['ensure.company.onboarded', 'ensure.inventory.access', 'ensure.subscribed']);

    Route::put('change-orders/{changeOrder}/approve', [PoChangeOrderController::class, 'approve'])
        ->middleware('ensure.company.onboarded');
    Route::put('change-orders/{changeOrder}/reject', [PoChangeOrderController::class, 'reject'])
        ->middleware('ensure.company.onboarded');

    Route::prefix('companies')->group(function (): void {
        Route::post('/', [CompanyRegistrationController::class, 'store']);
        Route::get('{company}', [CompanyRegistrationController::class, 'show']);
        Route::put('{company}', [CompanyRegistrationController::class, 'update']);

        Route::get('{company}/documents', [CompanyDocumentController::class, 'index']);
        Route::post('{company}/documents', [CompanyDocumentController::class, 'store']);
        Route::delete('{company}/documents/{document}', [CompanyDocumentController::class, 'destroy']);
    });

});

Route::middleware(['auth'])->prefix('invoices')->group(function (): void {
    Route::get('/', [InvoiceController::class, 'list'])->middleware(['ensure.company.onboarded', 'billing_access:read']);
    Route::get('{invoice}', [InvoiceController::class, 'show'])->middleware(['ensure.company.onboarded', 'billing_access:read']);
    Route::put('{invoice}', [InvoiceController::class, 'update'])->middleware(['ensure.company.onboarded', 'ensure.subscribed', 'billing_access:write']);
    Route::delete('{invoice}', [InvoiceController::class, 'destroy'])->middleware(['ensure.company.onboarded', 'ensure.subscribed', 'billing_access:write']);
    Route::post('from-po', [InvoiceController::class, 'storeFromPo'])->middleware(['ensure.company.onboarded', 'ensure.subscribed', 'billing_access:write']);
    Route::post('{invoice}/attachments', [InvoiceController::class, 'attachFile'])->middleware(['ensure.company.onboarded', 'ensure.subscribed', 'billing_access:write']);
    Route::post('{invoice}/review/approve', [InvoiceController::class, 'approve'])->middleware(['ensure.company.onboarded', 'ensure.subscribed', 'billing_access:write']);
    Route::post('{invoice}/review/reject', [InvoiceController::class, 'reject'])->middleware(['ensure.company.onboarded', 'ensure.subscribed', 'billing_access:write']);
    Route::post('{invoice}/review/request-changes', [InvoiceController::class, 'requestChanges'])->middleware(['ensure.company.onboarded', 'ensure.subscribed', 'billing_access:write']);
    Route::post('{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->middleware(['ensure.company.onboarded', 'ensure.subscribed', 'billing_access:write']);
});

Route::prefix('inventory')
    ->middleware(['auth', 'ensure.company.onboarded', 'ensure.inventory.access'])
    ->group(function (): void {
        Route::get('items', [InventoryItemController::class, 'index']);
        Route::post('items', [InventoryItemController::class, 'store']);
        Route::get('items/{item}', [InventoryItemController::class, 'show'])->whereNumber('item');
        Route::patch('items/{item}', [InventoryItemController::class, 'update'])->whereNumber('item');
        Route::get('low-stock', [InventoryLowStockController::class, 'index']);
        Route::get('movements', [InventoryMovementController::class, 'index']);
        Route::post('movements', [InventoryMovementController::class, 'store']);
        Route::get('locations', [InventoryLocationController::class, 'index']);
    });

Route::post('documents', [DocumentController::class, 'store'])
    ->middleware(['ensure.company.onboarded', 'ensure.subscribed']);
Route::get('documents/{document}', [DocumentController::class, 'show'])
    ->middleware(['ensure.company.onboarded', 'ensure.subscribed']);
Route::delete('documents/{document}', [DocumentController::class, 'destroy'])
    ->middleware(['ensure.company.onboarded', 'ensure.subscribed']);

Route::middleware(['ensure.company.onboarded'])->group(function (): void {
    Route::prefix('company-invitations')
        ->middleware('buyer_admin_only')
        ->group(function (): void {
            Route::get('/', [CompanyInvitationController::class, 'index']);
            Route::post('/', [CompanyInvitationController::class, 'store']);
            Route::delete('{invitation}', [CompanyInvitationController::class, 'destroy']);
        });

    Route::prefix('company-members')
        ->middleware('buyer_admin_only')
        ->group(function (): void {
            Route::get('/', [CompanyMemberController::class, 'index']);
            Route::patch('{member}', [CompanyMemberController::class, 'update']);
            Route::delete('{member}', [CompanyMemberController::class, 'destroy']);
        });

    Route::get('company-role-templates', [CompanyRoleTemplateController::class, 'index'])
        ->middleware('buyer_admin_only');

    Route::prefix('settings')
        ->middleware(['ensure.subscribed', 'buyer_admin_only'])
        ->group(function (): void {
            Route::get('company', [CompanySettingsController::class, 'show']);
            Route::patch('company', [CompanySettingsController::class, 'update']);

            Route::get('ai', [CompanyAiSettingsController::class, 'show']);
            Route::patch('ai', [CompanyAiSettingsController::class, 'update']);

            Route::middleware('ensure.localization.access')->group(function (): void {
                Route::get('localization', [LocaleSettingsController::class, 'show']);
                Route::patch('localization', [LocaleSettingsController::class, 'update']);
            });

            Route::get('numbering', [NumberingSettingsController::class, 'show']);
            Route::patch('numbering', [NumberingSettingsController::class, 'update']);
        });

    Route::get('dashboard/metrics', [DashboardController::class, 'metrics'])
        ->middleware('ensure.analytics.access');

    Route::middleware(['ensure.localization.access', 'apply.company.locale'])->prefix('localization')->group(function (): void {
        Route::get('settings', [LocaleSettingsController::class, 'show']);
        Route::put('settings', [LocaleSettingsController::class, 'update']);
        Route::get('uoms', [UomController::class, 'index']);
        Route::post('uoms', [UomController::class, 'store'])->middleware('buyer_admin_only');
        Route::put('uoms/{uom}', [UomController::class, 'update'])->middleware('buyer_admin_only');
        Route::delete('uoms/{uom}', [UomController::class, 'destroy'])->middleware('buyer_admin_only');
        Route::get('uoms/conversions', [UomConversionController::class, 'index']);
        Route::post('uoms/conversions', [UomConversionController::class, 'upsert'])->middleware('buyer_admin_only');
        Route::post('uom/convert', [UomConversionController::class, 'convert']);
        Route::get('parts/{part}/convert', [UomConversionController::class, 'convertForPart']);
    });

    Route::prefix('digital-twin')
        ->middleware(['ensure.subscribed', 'ensure.digital_twin.access'])
        ->group(function (): void {
            Route::apiResource('locations', DigitalTwinLocationController::class)->except(['create', 'edit']);
            Route::apiResource('systems', DigitalTwinSystemController::class)->except(['create', 'edit']);
            Route::apiResource('assets', DigitalTwinAssetController::class)->except(['create', 'edit']);
            Route::patch('assets/{asset}/status', [DigitalTwinAssetController::class, 'setStatus']);
            Route::put('assets/{asset}/bom', [DigitalTwinAssetBomController::class, 'sync']);
            Route::put('assets/{asset}/procedures/{procedure}', [DigitalTwinAssetMaintenanceController::class, 'link']);
            Route::delete('assets/{asset}/procedures/{procedure}', [DigitalTwinAssetMaintenanceController::class, 'detach']);
            Route::post('assets/{asset}/procedures/{procedure}/complete', [DigitalTwinAssetMaintenanceController::class, 'complete']);
            Route::apiResource('procedures', DigitalTwinMaintenanceProcedureController::class)->except(['create', 'edit']);
        });

    Route::middleware(['ensure.search.access'])->group(function (): void {
        Route::get('search', [SearchController::class, 'index']);
        Route::get('saved-searches', [SavedSearchController::class, 'index']);
        Route::post('saved-searches', [SavedSearchController::class, 'store']);
        Route::get('saved-searches/{savedSearch}', [SavedSearchController::class, 'show']);
        Route::put('saved-searches/{savedSearch}', [SavedSearchController::class, 'update']);
        Route::delete('saved-searches/{savedSearch}', [SavedSearchController::class, 'destroy']);
    });

    Route::prefix('analytics')->middleware('ensure.analytics.access')->group(function (): void {
        Route::get('overview', [AnalyticsController::class, 'overview']);
        Route::post('generate', [AnalyticsController::class, 'generate']);
    });

    Route::prefix('copilot')
        ->middleware([
            'auth',
            'ensure.company.onboarded:strict',
            'ensure.company.approved',
            'ensure.subscribed',
            'buyer_access',
            'ai.ensure.available',
            'ai.rate.limit',
        ])
        ->group(function (): void {
            Route::post('search', [CopilotSearchController::class, 'search']);
            Route::post('answer', [CopilotSearchController::class, 'answer']);
        });

    Route::post('copilot/analytics', [CopilotController::class, 'handle'])->middleware('ensure.analytics.access');

    Route::prefix('risk')->middleware(['ensure.subscribed', 'ensure.risk.access'])->group(function (): void {
        Route::get('/', [SupplierRiskController::class, 'index']);
        Route::get('{supplier}', [SupplierRiskController::class, 'show']);
        Route::post('generate', [SupplierRiskController::class, 'generate']);
    });

    Route::prefix('notifications')->middleware('ensure.notifications.access')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('read', [NotificationController::class, 'markSelectedRead']);
        Route::post('mark-all-read', [NotificationController::class, 'markAllRead']);
        Route::put('{notification}/read', [NotificationController::class, 'markRead']);
    });

    Route::prefix('events')->middleware('ensure.events.access')->group(function (): void {
        Route::get('deliveries', [EventDeliveryController::class, 'index']);
        Route::post('deliveries/{delivery}/retry', [EventDeliveryController::class, 'retry']);
        Route::post('dlq/replay', [EventDeliveryController::class, 'replay']);
    });

    Route::prefix('notification-preferences')->middleware('ensure.notifications.access')->group(function (): void {
        Route::get('/', [NotificationPreferenceController::class, 'index']);
        Route::put('/', [NotificationPreferenceController::class, 'update']);
    });

    Route::middleware(['ensure.subscribed', 'ensure.rma.access'])->prefix('rmas')->group(function (): void {
        Route::get('/', [RmaController::class, 'index']);
        Route::post('/purchase-orders/{purchaseOrder}', [RmaController::class, 'store']);
        Route::get('/{rma}', [RmaController::class, 'show']);
        Route::post('/{rma}/review', [RmaController::class, 'review']);
    });

    Route::prefix('exports')
        ->middleware(['ensure.subscribed', 'ensure.export.access'])
        ->group(function (): void {
            Route::get('/', [ExportController::class, 'index']);
            Route::post('/', [ExportController::class, 'store']);
            Route::get('/{exportRequest}', [ExportController::class, 'show']);
            Route::get('/{exportRequest}/download', [ExportController::class, 'download'])->name('exports.download');
        });

    Route::prefix('downloads')
        ->middleware(['ensure.company.onboarded', 'ensure.subscribed'])
        ->group(function (): void {
            Route::get('/', [DownloadJobController::class, 'index']);
            Route::post('/', [DownloadJobController::class, 'store']);
            Route::get('{downloadJob}', [DownloadJobController::class, 'show']);
            Route::post('{downloadJob}/retry', [DownloadJobController::class, 'retry']);
            Route::get('{downloadJob}/file', [DownloadJobController::class, 'download'])->name('downloads.file');
        });

    Route::middleware(['ensure.subscribed', 'ensure.credit_notes.access'])->prefix('credit-notes')->group(function (): void {
        Route::get('/', [CreditNoteController::class, 'index']);
        Route::post('/invoices/{invoice}', [CreditNoteController::class, 'store']);
        Route::get('/{creditNote}', [CreditNoteController::class, 'show']);
        Route::post('/{creditNote}/issue', [CreditNoteController::class, 'issue']);
        Route::post('/{creditNote}/approve', [CreditNoteController::class, 'approve']);
        Route::put('/{creditNote}/lines', [CreditNoteController::class, 'updateLines']);
        Route::post('/{creditNote}/attachments', [CreditNoteController::class, 'attachFile']);
    });

    Route::prefix('money')->middleware('ensure.subscribed')->group(function (): void {
        Route::middleware('ensure.money.access')->group(function (): void {
            Route::get('settings', [MoneySettingsController::class, 'show']);
            Route::put('settings', [MoneySettingsController::class, 'update']);
        });

        Route::middleware('ensure.money.access:billing')->group(function (): void {
            Route::get('fx', [FxRateController::class, 'index']);
            Route::post('fx', [FxRateController::class, 'upsert']);
            Route::apiResource('tax-codes', TaxCodeController::class)->except(['create', 'edit']);
        });
    });

    Route::middleware(['ensure.subscribed', 'ensure.money.access:billing'])->group(function (): void {
        Route::post('quotes/{quote}/recalculate', [QuoteTotalsController::class, 'recalculate']);
        Route::post('purchase-orders/{purchaseOrder}/recalculate', [PoTotalsController::class, 'recalculate']);
        Route::post('invoices/{invoice}/recalculate', [InvoiceTotalsController::class, 'recalculate']);
        Route::post('credit-notes/{creditNote}/recalculate', [CreditTotalsController::class, 'recalculate']);
    });

    Route::prefix('approvals')
        ->middleware(['ensure.approvals.access'])
        ->group(function (): void {
            Route::get('rules', [ApprovalRuleController::class, 'index']);
            Route::get('rules/{rule}', [ApprovalRuleController::class, 'show']);
            Route::post('rules', [ApprovalRuleController::class, 'store'])->middleware('buyer_admin_only');
            Route::put('rules/{rule}', [ApprovalRuleController::class, 'update'])->middleware('buyer_admin_only');
            Route::delete('rules/{rule}', [ApprovalRuleController::class, 'destroy'])->middleware('buyer_admin_only');

            Route::get('requests', [ApprovalRequestController::class, 'index']);
            Route::get('requests/{approval}', [ApprovalRequestController::class, 'show']);
            Route::post('requests/{approval}/action', [ApprovalRequestController::class, 'action']);

            Route::get('delegations', [DelegationController::class, 'index']);
            Route::post('delegations', [DelegationController::class, 'store'])->middleware('buyer_admin_only');
            Route::delete('delegations/{delegation}', [DelegationController::class, 'destroy'])->middleware('buyer_admin_only');
            Route::put('delegations/{delegation}', [DelegationController::class, 'store'])->middleware('buyer_admin_only');
        });
});

Route::prefix('webhooks/stripe')->group(function (): void {
    Route::post('invoice/payment-succeeded', [StripeWebhookController::class, 'invoicePaymentSucceeded']);
    Route::post('invoice/payment-failed', [StripeWebhookController::class, 'invoicePaymentFailed']);
    Route::post('customer/subscription-updated', [StripeWebhookController::class, 'customerSubscriptionUpdated']);
});

Route::post('billing/stripe/webhook', [StripeWebhookController::class, 'catchAll']);
