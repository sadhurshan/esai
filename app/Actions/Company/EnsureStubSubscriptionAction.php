<?php

namespace App\Actions\Company;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class EnsureStubSubscriptionAction
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function execute(Company $company, Plan $plan, ?Carbon $trialEndsAt = null): Subscription
    {
        $customer = $this->ensureCustomer($company);

        return $this->ensureSubscription($company, $plan, $customer, $trialEndsAt);
    }

    private function ensureCustomer(Company $company): Customer
    {
        $stripeId = 'cust-stub-'.$company->id;

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
                $after = Arr::only($customer->attributesToArray(), array_keys($dirty));
                $this->auditLogger->updated($customer, $before, $after);
            }
        }

        return $customer;
    }

    private function ensureSubscription(Company $company, Plan $plan, Customer $customer, ?Carbon $trialEndsAt = null): Subscription
    {
        $stripeId = 'sub-stub-'.$company->id;
        $subscription = Subscription::firstOrNew(['stripe_id' => $stripeId]);
        $before = $subscription->exists ? $subscription->getOriginal() : [];

        $subscription->company_id = $company->id;
        $subscription->customer_id = $customer->id;
        $subscription->name = 'primary';
        $subscription->stripe_status = $trialEndsAt ? 'trialing' : 'active';
        $subscription->stripe_plan = $plan->code;
        $subscription->quantity = 1;
        $subscription->trial_ends_at = $trialEndsAt;
        $subscription->ends_at = null;

        $dirty = $subscription->getDirty();

        if ($dirty !== [] || ! $subscription->exists) {
            $subscription->save();

            if ($subscription->wasRecentlyCreated) {
                $this->auditLogger->created($subscription);
            } elseif ($dirty !== []) {
                $after = Arr::only($subscription->attributesToArray(), array_keys($dirty));
                $this->auditLogger->updated($subscription, $before, $after);
            }
        }

        return $subscription;
    }
}
