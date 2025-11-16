<?php

namespace App\Actions\Company;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\Audit\AuditLogger;

class EnsureComplimentarySubscriptionAction
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function supports(Plan $plan): bool
    {
        if ($plan->code === 'community') {
            return true;
        }

        $price = $plan->price_usd;

        return $price === null || (float) $price <= 0;
    }

    public function execute(Company $company, Plan $plan): ?Subscription
    {
        if (! $this->supports($plan)) {
            return null;
        }

        $customer = $this->ensureCustomer($company);

        return $this->ensureSubscription($company, $plan, $customer);
    }

    private function ensureCustomer(Company $company): Customer
    {
        $stripeId = 'cust-free-'.$company->id;

        $customer = Customer::firstOrNew(['stripe_id' => $stripeId]);
        $before = $customer->exists ? $customer->getOriginal() : [];

        $customer->company_id = $company->id;
        $customer->name = $customer->name ?: $company->name;
        $customer->email = $customer->email ?: ($company->owner?->email ?? $company->primary_contact_email);

        $dirty = $customer->getDirty();

        if ($dirty !== [] || ! $customer->exists) {
            $customer->save();

            if ($customer->wasRecentlyCreated) {
                $this->auditLogger->created($customer);
            } elseif ($dirty !== []) {
                $after = array_intersect_key($customer->attributesToArray(), $dirty);
                $this->auditLogger->updated($customer, $before, $after);
            }
        }

        return $customer;
    }

    private function ensureSubscription(Company $company, Plan $plan, Customer $customer): Subscription
    {
        $stripeId = 'sub-free-'.$company->id;
        $subscription = Subscription::firstOrNew(['stripe_id' => $stripeId]);
        $before = $subscription->exists ? $subscription->getOriginal() : [];

        $subscription->company_id = $company->id;
        $subscription->customer_id = $customer->id;
        $subscription->name = 'community';
        $subscription->stripe_status = 'active';
        $subscription->stripe_plan = $plan->code;
        $subscription->quantity = 1;
        $subscription->trial_ends_at = null;
        $subscription->ends_at = null;

        $dirty = $subscription->getDirty();

        if ($dirty !== [] || ! $subscription->exists) {
            $subscription->save();

            if ($subscription->wasRecentlyCreated) {
                $this->auditLogger->created($subscription);
            } elseif ($dirty !== []) {
                $after = array_intersect_key($subscription->attributesToArray(), $dirty);
                $this->auditLogger->updated($subscription, $before, $after);
            }
        }

        return $subscription;
    }
}
