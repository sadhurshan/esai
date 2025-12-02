<?php

namespace App\Http\Controllers\Api\Billing;

use App\Exceptions\StripeWebhookException;
use App\Http\Controllers\Api\ApiController;
use App\Models\AuditLog;
use App\Services\Billing\StripeWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends ApiController
{
    public function __construct(private readonly StripeWebhookService $stripeWebhookService)
    {
    }

    public function invoicePaymentSucceeded(Request $request): JsonResponse
    {
        return $this->handle($request, 'invoice.payment_succeeded', fn ($event) => $this->stripeWebhookService->handleInvoicePaymentSucceeded($event));
    }

    public function invoicePaymentFailed(Request $request): JsonResponse
    {
        return $this->handle($request, 'invoice.payment_failed', fn ($event) => $this->stripeWebhookService->handleInvoicePaymentFailed($event));
    }

    public function customerSubscriptionUpdated(Request $request): JsonResponse
    {
        return $this->handle($request, 'customer.subscription.updated', fn ($event) => $this->stripeWebhookService->handleCustomerSubscriptionUpdated($event));
    }

    public function catchAll(Request $request): JsonResponse
    {
        try {
            $event = $this->stripeWebhookService->verify($request);
        } catch (StripeWebhookException $exception) {
            Log::warning('Stripe webhook rejected', [
                'expected' => null,
                'error' => $exception->getMessage(),
            ]);

            return $this->fail($exception->getMessage(), 400);
        }

        $eventType = (string) ($event->type ?? 'unknown');
        $this->recordWebhook($eventType, $request->all());

        $processed = match ($eventType) {
            'invoice.payment_succeeded' => $this->stripeWebhookService->handleInvoicePaymentSucceeded($event) !== null,
            'invoice.payment_failed' => $this->stripeWebhookService->handleInvoicePaymentFailed($event) !== null,
            'customer.subscription.updated' => $this->stripeWebhookService->handleCustomerSubscriptionUpdated($event) !== null,
            'customer.subscription.created' => $this->stripeWebhookService->handleCustomerSubscriptionUpdated($event) !== null,
            default => false,
        };

        if (! $processed) {
            Log::info('Stripe webhook ignored', [
                'event' => $eventType,
            ]);
        }

        return $this->ok([
            'received' => true,
            'processed' => $processed,
            'event' => $eventType,
        ]);
    }

    private function handle(Request $request, string $expectedEvent, callable $callback): JsonResponse
    {
        try {
            $event = $this->stripeWebhookService->verify($request, $expectedEvent);
        } catch (StripeWebhookException $exception) {
            Log::warning('Stripe webhook rejected', [
                'expected' => $expectedEvent,
                'error' => $exception->getMessage(),
            ]);

            return $this->fail($exception->getMessage(), 400);
        }

        $this->recordWebhook($expectedEvent, $request->all());

        $result = $callback($event);

        return $this->ok([
            'received' => true,
            'processed' => $result !== null,
        ]);
    }

    private function recordWebhook(string $event, array $payload): void
    {
        AuditLog::create([
            'company_id' => null,
            'user_id' => null,
            'entity_type' => 'stripe.webhook',
            'entity_id' => 0,
            'action' => 'created',
            'before' => null,
            'after' => [
                'event' => $event,
                'payload' => $payload,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
