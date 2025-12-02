<?php

namespace App\Services\Billing;

use App\Actions\Company\AssignCompanyPlanAction;
use App\Exceptions\StripeWebhookException;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\Event as StripeEvent;
use Stripe\StripeObject;
use Stripe\Webhook as StripeWebhook;

class StripeWebhookService
{
    public function __construct(
        private readonly AssignCompanyPlanAction $assignCompanyPlanAction,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function verify(Request $request, ?string $expectedType = null): StripeEvent
    {
        $secret = config('services.stripe.webhook_secret');

        if (blank($secret)) {
            throw StripeWebhookException::missingSecret();
        }

        $signature = $request->header('Stripe-Signature');

        if (blank($signature)) {
            throw StripeWebhookException::missingSignature();
        }

        try {
            $event = StripeWebhook::constructEvent($request->getContent(), $signature, $secret);
        } catch (\Throwable $exception) {
            throw StripeWebhookException::invalidPayload($exception);
        }

        if ($expectedType !== null && $event->type !== $expectedType) {
            throw StripeWebhookException::unexpectedEvent($expectedType, (string) ($event->type ?? 'unknown'));
        }

        return $event;
    }

    public function handleInvoicePaymentSucceeded(StripeEvent $event): ?Subscription
    {
        $invoice = $this->toArray($event->data->object ?? []);

        return $this->syncInvoiceSubscription($invoice, 'active', null, $invoice['customer_email'] ?? null);
    }

    public function handleInvoicePaymentFailed(StripeEvent $event): ?Subscription
    {
        $invoice = $this->toArray($event->data->object ?? []);
        $graceDays = (int) config('services.stripe.past_due_grace_days', 7);
        $graceUntil = Carbon::now()->addDays($graceDays);

        return $this->syncInvoiceSubscription($invoice, 'past_due', $graceUntil, $invoice['customer_email'] ?? null);
    }

    public function handleCustomerSubscriptionUpdated(StripeEvent $event): ?Subscription
    {
        $subscription = $this->toArray($event->data->object ?? []);

        $company = $this->resolveCompany($subscription['customer'] ?? null, $subscription['metadata'] ?? []);

        if (! $company) {
            Log::warning('Stripe subscription webhook without company context', [
                'subscription_id' => $subscription['id'] ?? null,
                'customer' => $subscription['customer'] ?? null,
            ]);

            return null;
        }

        $customer = $this->ensureCustomer(
            $company,
            $subscription['customer'] ?? null,
            $subscription['customer_email'] ?? null,
            $subscription['metadata'] ?? []
        );

        $planCode = $subscription['metadata']['plan_code'] ?? $this->planCodeFromPrice($this->subscriptionPriceId($subscription));
        $plan = $planCode ? Plan::query()->firstWhere('code', $planCode) : null;

        $record = $this->upsertSubscription($company, $customer, [
            'stripe_id' => $subscription['id'] ?? null,
            'stripe_status' => $subscription['status'] ?? null,
            'stripe_plan' => $this->subscriptionPriceId($subscription),
            'quantity' => $this->subscriptionQuantity($subscription),
            'trial_ends_at' => $this->timestampOrNull($subscription['trial_end'] ?? null),
            'ends_at' => $this->timestampOrNull($subscription['ended_at'] ?? $subscription['cancel_at'] ?? null),
            'name' => $subscription['metadata']['name'] ?? 'primary',
        ]);

        if ($plan) {
            $this->syncCompanyPlan($company, $plan);
        }

        return $record;
    }

    private function syncInvoiceSubscription(array $invoice, string $status, ?Carbon $endsAt, ?string $customerEmail): ?Subscription
    {
        $subscriptionId = $invoice['subscription'] ?? null;
        $stripeCustomerId = $invoice['customer'] ?? null;

        if (! $subscriptionId || ! $stripeCustomerId) {
            return null;
        }

        $company = $this->resolveCompany($stripeCustomerId, $invoice['metadata'] ?? []);

        if (! $company) {
            Log::warning('Stripe invoice webhook without company context', [
                'invoice_id' => $invoice['id'] ?? null,
                'subscription_id' => $subscriptionId,
            ]);

            return null;
        }

        $customer = $this->ensureCustomer($company, $stripeCustomerId, $customerEmail, $invoice['metadata'] ?? []);

        $record = $this->upsertSubscription($company, $customer, [
            'stripe_id' => $subscriptionId,
            'stripe_status' => $status,
            'stripe_plan' => $this->invoicePriceId($invoice),
            'quantity' => $this->invoiceQuantity($invoice),
            'trial_ends_at' => $this->timestampOrNull($invoice['trial_end'] ?? null),
            'ends_at' => $endsAt,
            'name' => 'primary',
        ]);

        if (($invoice['lines']['data'][0]['price']['id'] ?? null) !== null) {
            $planCode = $this->planCodeFromPrice($invoice['lines']['data'][0]['price']['id']);
            $plan = $planCode ? Plan::query()->firstWhere('code', $planCode) : null;

            if ($plan) {
                $this->syncCompanyPlan($company, $plan);
            }
        }

        if ($status === 'active' && $record && $record->ends_at !== null) {
            $record->forceFill(['ends_at' => null])->save();
        }

        return $record;
    }

    private function upsertSubscription(Company $company, Customer $customer, array $attributes): ?Subscription
    {
        if (blank($attributes['stripe_id'] ?? null)) {
            return null;
        }

        $subscription = Subscription::firstOrNew(['stripe_id' => $attributes['stripe_id']]);
        $before = $subscription->exists ? $subscription->getOriginal() : [];

        $subscription->company_id = $company->id;
        $subscription->customer_id = $customer->id;
        if (array_key_exists('name', $attributes)) {
            $subscription->name = $attributes['name'] ?? 'primary';
        }

        if (array_key_exists('stripe_status', $attributes)) {
            $subscription->stripe_status = $attributes['stripe_status'];
        }

        if (array_key_exists('stripe_plan', $attributes)) {
            $subscription->stripe_plan = $attributes['stripe_plan'];
        }

        if (array_key_exists('quantity', $attributes)) {
            $subscription->quantity = $attributes['quantity'];
        }

        if (array_key_exists('trial_ends_at', $attributes)) {
            $subscription->trial_ends_at = $attributes['trial_ends_at'];
        }

        if (array_key_exists('ends_at', $attributes)) {
            $subscription->ends_at = $attributes['ends_at'];
        }

        $dirty = $subscription->getDirty();

        $subscription->save();

        if ($subscription->wasRecentlyCreated) {
            $this->auditLogger->created($subscription);
        } elseif ($dirty !== []) {
            $after = array_intersect_key($subscription->attributesToArray(), $dirty);
            $this->auditLogger->updated($subscription, $before, $after);
        }

        return $subscription;
    }

    private function ensureCustomer(Company $company, ?string $stripeCustomerId, ?string $email = null, array $metadata = []): Customer
    {
        if (blank($stripeCustomerId)) {
            $stripeCustomerId = $company->stripe_id;
        }

        if (blank($stripeCustomerId)) {
            throw StripeWebhookException::invalidPayload(new \RuntimeException('Missing Stripe customer reference.'));
        }

        $customer = Customer::firstOrNew(['stripe_id' => $stripeCustomerId]);

        $customer->company_id = $company->id;
        $customer->name = $customer->name ?: $company->name;
        $customer->email = $customer->email ?: ($email ?? $metadata['customer_email'] ?? $company->owner?->email);

        if (! $customer->exists) {
            $customer->save();
        } elseif ($customer->isDirty()) {
            $customer->save();
        }

        if (! $company->stripe_id && $stripeCustomerId) {
            $company->forceFill(['stripe_id' => $stripeCustomerId])->save();
        }

        return $customer;
    }

    private function resolveCompany(?string $stripeCustomerId, array $metadata = []): ?Company
    {
        if ($stripeCustomerId) {
            $customer = Customer::query()->where('stripe_id', $stripeCustomerId)->first();

            if ($customer?->company) {
                return $customer->company;
            }

            $company = Company::query()->where('stripe_id', $stripeCustomerId)->first();

            if ($company) {
                return $company;
            }
        }

        $companyId = $metadata['company_id']
            ?? $metadata['companyId']
            ?? null;

        if ($companyId) {
            return Company::query()->find($companyId);
        }

        return null;
    }

    private function syncCompanyPlan(Company $company, Plan $plan): void
    {
        if ($company->plan_id === $plan->id) {
            return;
        }

        $this->assignCompanyPlanAction->execute($company, $plan);
    }

    private function planCodeFromPrice(?string $priceId): ?string
    {
        if (! $priceId) {
            return null;
        }

        $prices = config('services.stripe.prices', []);

        foreach ($prices as $code => $configuredId) {
            if ($configuredId && $configuredId === $priceId) {
                return $code;
            }
        }

        return null;
    }

    private function subscriptionPriceId(array $subscription): ?string
    {
        return $subscription['items']['data'][0]['price']['id'] ?? null;
    }

    private function subscriptionQuantity(array $subscription): ?int
    {
        $quantity = $subscription['items']['data'][0]['quantity'] ?? null;

        return $quantity !== null ? (int) $quantity : null;
    }

    private function invoicePriceId(array $invoice): ?string
    {
        return $invoice['lines']['data'][0]['price']['id'] ?? null;
    }

    private function invoiceQuantity(array $invoice): ?int
    {
        $quantity = $invoice['lines']['data'][0]['quantity'] ?? null;

        return $quantity !== null ? (int) $quantity : null;
    }

    private function timestampOrNull(?int $timestamp): ?Carbon
    {
        if (! $timestamp) {
            return null;
        }

        return Carbon::createFromTimestamp($timestamp);
    }

    private function toArray(mixed $object): array
    {
        if ($object instanceof StripeObject) {
            return $object->toArray();
        }

        if (is_array($object)) {
            return $object;
        }

        return (array) $object;
    }
}
