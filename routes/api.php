<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AwardController;
use App\Http\Controllers\Api\Billing\StripeWebhookController;
use App\Http\Controllers\Api\CopilotController;
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
use App\Http\Controllers\Api\RfqInvitationController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierApplicationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\Admin\CompanyApprovalController;
use App\Http\Controllers\Api\Admin\SupplierApplicationReviewController;
use Illuminate\Support\Facades\Route;

Route::get('health', [HealthController::class, '__invoke']);

Route::prefix('files')->group(function (): void {
    Route::get('cad/{rfq}', [FileController::class, 'cad']);
    Route::get('attachments/{quote}', [FileController::class, 'attachment']);
});

Route::prefix('suppliers')->group(function (): void {
    Route::get('/', [SupplierController::class, 'index']);
    Route::get('{supplier}', [SupplierController::class, 'show']);
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

    Route::post('{rfq}/award', [AwardController::class, 'store'])->middleware('ensure.company.onboarded');
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
    Route::prefix('analytics')->middleware('ensure.analytics.access')->group(function (): void {
        Route::get('overview', [AnalyticsController::class, 'overview']);
        Route::post('generate', [AnalyticsController::class, 'generate']);
    });

    Route::post('copilot/analytics', [CopilotController::class, 'handle'])->middleware('ensure.analytics.access');

    Route::prefix('notifications')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('mark-all-read', [NotificationController::class, 'markAllRead']);
        Route::put('{notification}/read', [NotificationController::class, 'markRead']);
    });

    Route::prefix('notification-preferences')->group(function (): void {
        Route::get('/', [NotificationPreferenceController::class, 'index']);
        Route::put('/', [NotificationPreferenceController::class, 'update']);
    });
});

Route::prefix('webhooks/stripe')->group(function (): void {
    Route::post('invoice/payment-succeeded', [StripeWebhookController::class, 'invoicePaymentSucceeded']);
    Route::post('invoice/payment-failed', [StripeWebhookController::class, 'invoicePaymentFailed']);
    Route::post('customer/subscription-updated', [StripeWebhookController::class, 'customerSubscriptionUpdated']);
});
