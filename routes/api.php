<?php

use App\Http\Controllers\Api\Billing\StripeWebhookController;
use App\Http\Controllers\Api\AwardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\RFQController;
use App\Http\Controllers\Api\PoChangeOrderController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\RfqInvitationController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\SupplierController;
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

Route::prefix('rfqs')->group(function (): void {
    Route::get('/', [RFQController::class, 'index']);
    Route::post('/', [RFQController::class, 'store'])->middleware('ensure.subscribed');
    Route::get('{rfq}', [RFQController::class, 'show']);
    Route::put('{rfq}', [RFQController::class, 'update']);
    Route::delete('{rfq}', [RFQController::class, 'destroy']);

    Route::get('{rfq}/invitations', [RfqInvitationController::class, 'index']);
    Route::post('{rfq}/invitations', [RfqInvitationController::class, 'store']);

    Route::get('{rfq}/quotes', [QuoteController::class, 'index']);

    Route::post('{rfq}/award', [AwardController::class, 'store']);
});

Route::prefix('orders')->group(function (): void {
    Route::get('/', [OrderController::class, 'index']);
    Route::get('{order}', [OrderController::class, 'show']);
});

Route::post('quotes', [QuoteController::class, 'store'])->middleware('ensure.subscribed');

Route::middleware('web')->group(function (): void {
    Route::prefix('purchase-orders')->group(function (): void {
        Route::get('/', [PurchaseOrderController::class, 'index']);
        Route::post('{purchaseOrder}/send', [PurchaseOrderController::class, 'send']);
        Route::post('{purchaseOrder}/acknowledge', [PurchaseOrderController::class, 'acknowledge']);
        Route::get('{purchaseOrder}/change-orders', [PoChangeOrderController::class, 'index']);
        Route::post('{purchaseOrder}/change-orders', [PoChangeOrderController::class, 'store'])
            ->middleware('ensure.subscribed');
        Route::get('{purchaseOrder}', [PurchaseOrderController::class, 'show']);
    });

    Route::put('change-orders/{changeOrder}/approve', [PoChangeOrderController::class, 'approve']);
    Route::put('change-orders/{changeOrder}/reject', [PoChangeOrderController::class, 'reject']);
});

Route::post('documents', [DocumentController::class, 'store'])
    ->middleware('ensure.subscribed');

Route::prefix('webhooks/stripe')->group(function (): void {
    Route::post('invoice/payment-succeeded', [StripeWebhookController::class, 'invoicePaymentSucceeded']);
    Route::post('invoice/payment-failed', [StripeWebhookController::class, 'invoicePaymentFailed']);
    Route::post('customer/subscription-updated', [StripeWebhookController::class, 'customerSubscriptionUpdated']);
});
