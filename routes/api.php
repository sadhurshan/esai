<?php

use App\Http\Controllers\Api\Billing\StripeWebhookController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\RFQController;
use App\Http\Controllers\Api\RFQQuoteController;
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

    Route::get('{rfq}/quotes', [RFQQuoteController::class, 'index']);
    Route::post('{rfq}/quotes', [RFQQuoteController::class, 'store'])->middleware('ensure.subscribed');
});

Route::prefix('orders')->group(function (): void {
    Route::get('/', [OrderController::class, 'index']);
    Route::get('{order}', [OrderController::class, 'show']);
});

Route::post('documents', [DocumentController::class, 'store'])
    ->middleware('ensure.subscribed');

Route::prefix('webhooks/stripe')->group(function (): void {
    Route::post('invoice/payment-succeeded', [StripeWebhookController::class, 'invoicePaymentSucceeded']);
    Route::post('invoice/payment-failed', [StripeWebhookController::class, 'invoicePaymentFailed']);
    Route::post('customer/subscription-updated', [StripeWebhookController::class, 'customerSubscriptionUpdated']);
});
