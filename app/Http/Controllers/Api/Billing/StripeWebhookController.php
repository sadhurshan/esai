<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Api\ApiController;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends ApiController
{
    public function invoicePaymentSucceeded(Request $request): JsonResponse
    {
        $this->recordWebhook('invoice.payment_succeeded', $request->all());

        // TODO: clarify with spec - map Stripe events to billing state transitions.
        return $this->ok(['received' => true]);
    }

    public function invoicePaymentFailed(Request $request): JsonResponse
    {
        $this->recordWebhook('invoice.payment_failed', $request->all());

        // TODO: clarify with spec - trigger dunning and downgrade workflows.
        return $this->ok(['received' => true]);
    }

    public function customerSubscriptionUpdated(Request $request): JsonResponse
    {
        $this->recordWebhook('customer.subscription.updated', $request->all());

        // TODO: clarify with spec - sync subscription status and plan changes.
        return $this->ok(['received' => true]);
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
