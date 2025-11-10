<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AwardController;
use App\Http\Controllers\Api\Billing\StripeWebhookController;
use App\Http\Controllers\Api\CopilotController;
use App\Http\Controllers\Api\SupplierRiskController;
use App\Http\Controllers\Api\SupplierEsgController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\CompanyDocumentController;
use App\Http\Controllers\Api\CompanyRegistrationController;
use App\Http\Controllers\Api\GoodsReceiptNoteController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\RFQController;
use App\Http\Controllers\Api\PoChangeOrderController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\RfqClarificationController;
use App\Http\Controllers\Api\QuoteRevisionController;
use App\Http\Controllers\Api\RfqAwardController;
use App\Http\Controllers\Api\RfqInvitationController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierApplicationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\SavedSearchController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\Admin\CompanyApprovalController;
use App\Http\Controllers\Api\Admin\SupplierApplicationReviewController;
use App\Http\Controllers\Api\ApprovalRuleController;
use App\Http\Controllers\Api\ApprovalRequestController;
use App\Http\Controllers\Api\DelegationController;
use App\Http\Controllers\Api\RmaController;
use App\Http\Controllers\Api\CreditNoteController;
use Illuminate\Support\Facades\Route;

Route::get('health', [HealthController::class, '__invoke']);

Route::prefix('files')->group(function (): void {
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

Route::prefix('supplier-applications')->group(function (): void {
    Route::get('/', [SupplierApplicationController::class, 'index']);
    Route::post('/', [SupplierApplicationController::class, 'store']);
    Route::get('{application}', [SupplierApplicationController::class, 'show']);
    Route::delete('{application}', [SupplierApplicationController::class, 'destroy']);
});


Route::prefix('rfqs')->group(function (): void {
    Route::get('/', [RFQController::class, 'index']);
    Route::post('/', [RFQController::class, 'store'])->middleware(['ensure.company.onboarded', 'ensure.subscribed']);
    Route::get('{rfq}', [RFQController::class, 'show']);
    Route::put('{rfq}', [RFQController::class, 'update'])->middleware('ensure.company.onboarded');
    Route::delete('{rfq}', [RFQController::class, 'destroy'])->middleware('ensure.company.onboarded');

    Route::get('{rfq}/invitations', [RfqInvitationController::class, 'index']);
    Route::post('{rfq}/invitations', [RfqInvitationController::class, 'store'])->middleware('ensure.company.onboarded');

    Route::get('{rfq}/quotes', [QuoteController::class, 'index']);

    Route::prefix('{rfq}/quotes/{quote}')
        ->middleware(['ensure.company.onboarded', 'ensure.subscribed', 'ensure.supplier.approved'])
        ->group(function (): void {
            Route::get('/revisions', [QuoteRevisionController::class, 'index'])
                ->withoutMiddleware('ensure.supplier.approved');
            Route::post('/revisions', [QuoteRevisionController::class, 'store']);
            Route::post('/withdraw', [QuoteController::class, 'withdraw']);
        });

    Route::post('{rfq}/award', [AwardController::class, 'store'])->middleware('ensure.company.onboarded');
    Route::post('{rfq}/award-lines', [RfqAwardController::class, 'awardLines'])
        ->middleware(['ensure.company.onboarded', 'ensure.subscribed']);

    Route::prefix('{rfq}/clarifications')
        ->middleware(['ensure.company.onboarded', 'ensure.subscribed'])
        ->group(function (): void {
            Route::get('/', [RfqClarificationController::class, 'index']);
            Route::post('/question', [RfqClarificationController::class, 'storeQuestion']);
            Route::post('/answer', [RfqClarificationController::class, 'storeAnswer']);
            Route::post('/amendment', [RfqClarificationController::class, 'storeAmendment']);
        });
});

Route::prefix('orders')->group(function (): void {
    Route::get('/', [OrderController::class, 'index']);
    Route::get('{order}', [OrderController::class, 'show']);
});

Route::post('quotes', [QuoteController::class, 'store'])->middleware(['ensure.subscribed', 'ensure.supplier.approved']);

Route::middleware('web')->group(function (): void {
    Route::prefix('purchase-orders')->group(function (): void {
        Route::get('/', [PurchaseOrderController::class, 'index']);
        Route::post('{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])->middleware('ensure.company.onboarded');
        Route::post('{purchaseOrder}/acknowledge', [PurchaseOrderController::class, 'acknowledge'])
            ->middleware('ensure.supplier.approved');
        Route::get('{purchaseOrder}/change-orders', [PoChangeOrderController::class, 'index']);
        Route::post('{purchaseOrder}/change-orders', [PoChangeOrderController::class, 'store'])
            ->middleware(['ensure.company.onboarded', 'ensure.subscribed']);
        Route::get('{purchaseOrder}/invoices', [InvoiceController::class, 'index'])
            ->middleware('ensure.company.onboarded');
        Route::post('{purchaseOrder}/invoices', [InvoiceController::class, 'store'])
            ->middleware(['ensure.company.onboarded', 'ensure.subscribed']);
        Route::get('{purchaseOrder}/grns', [GoodsReceiptNoteController::class, 'index'])
            ->middleware('ensure.company.onboarded');
        Route::post('{purchaseOrder}/grns', [GoodsReceiptNoteController::class, 'store'])
            ->middleware(['ensure.company.onboarded', 'ensure.subscribed']);
        Route::get('{purchaseOrder}/grns/{note}', [GoodsReceiptNoteController::class, 'show'])
            ->middleware('ensure.company.onboarded');
        Route::put('{purchaseOrder}/grns/{note}', [GoodsReceiptNoteController::class, 'update'])
            ->middleware(['ensure.company.onboarded', 'ensure.subscribed']);
        Route::delete('{purchaseOrder}/grns/{note}', [GoodsReceiptNoteController::class, 'destroy'])
            ->middleware(['ensure.company.onboarded', 'ensure.subscribed']);
        Route::get('{purchaseOrder}', [PurchaseOrderController::class, 'show']);
    });

    Route::prefix('invoices')->group(function (): void {
        Route::get('{invoice}', [InvoiceController::class, 'show'])->middleware('ensure.company.onboarded');
        Route::put('{invoice}', [InvoiceController::class, 'update'])->middleware(['ensure.company.onboarded', 'ensure.subscribed']);
        Route::delete('{invoice}', [InvoiceController::class, 'destroy'])->middleware(['ensure.company.onboarded', 'ensure.subscribed']);
    });

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

    Route::prefix('admin/companies')->group(function (): void {
        Route::get('/', [CompanyApprovalController::class, 'index']);
        Route::post('{company}/approve', [CompanyApprovalController::class, 'approve']);
        Route::post('{company}/reject', [CompanyApprovalController::class, 'reject']);
    });

    Route::prefix('admin/supplier-applications')->group(function (): void {
        Route::get('/', [SupplierApplicationReviewController::class, 'index']);
        Route::post('{application}/approve', [SupplierApplicationReviewController::class, 'approve']);
        Route::post('{application}/reject', [SupplierApplicationReviewController::class, 'reject']);
    });
});

Route::post('documents', [DocumentController::class, 'store'])
    ->middleware(['ensure.company.onboarded', 'ensure.subscribed']);

Route::middleware(['ensure.company.onboarded'])->group(function (): void {
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

    Route::post('copilot/analytics', [CopilotController::class, 'handle'])->middleware('ensure.analytics.access');

    Route::prefix('risk')->middleware(['ensure.subscribed', 'ensure.risk.access'])->group(function (): void {
        Route::get('/', [SupplierRiskController::class, 'index']);
        Route::get('{supplier}', [SupplierRiskController::class, 'show']);
        Route::post('generate', [SupplierRiskController::class, 'generate']);
    });

    Route::prefix('notifications')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('mark-all-read', [NotificationController::class, 'markAllRead']);
        Route::put('{notification}/read', [NotificationController::class, 'markRead']);
    });

    Route::prefix('notification-preferences')->group(function (): void {
        Route::get('/', [NotificationPreferenceController::class, 'index']);
        Route::put('/', [NotificationPreferenceController::class, 'update']);
    });

    Route::middleware(['ensure.subscribed', 'ensure.rma.access'])->prefix('rmas')->group(function (): void {
        Route::get('/', [RmaController::class, 'index']);
        Route::post('/purchase-orders/{purchaseOrder}', [RmaController::class, 'store']);
        Route::get('/{rma}', [RmaController::class, 'show']);
        Route::post('/{rma}/review', [RmaController::class, 'review']);
    });

    Route::middleware(['ensure.subscribed', 'ensure.credit_notes.access'])->prefix('credit-notes')->group(function (): void {
        Route::get('/', [CreditNoteController::class, 'index']);
        Route::post('/invoices/{invoice}', [CreditNoteController::class, 'store']);
        Route::get('/{creditNote}', [CreditNoteController::class, 'show']);
        Route::post('/{creditNote}/issue', [CreditNoteController::class, 'issue']);
        Route::post('/{creditNote}/approve', [CreditNoteController::class, 'approve']);
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
