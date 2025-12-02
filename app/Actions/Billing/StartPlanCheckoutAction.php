<?php

namespace App\Actions\Billing;

use App\Actions\Company\EnsureStubSubscriptionAction;
use App\Models\Company;
use App\Models\Plan;
use App\Services\Billing\StripeCheckoutService;
use Illuminate\Support\Arr;

class StartPlanCheckoutAction
{
    public function __construct(
        private readonly StripeCheckoutService $checkoutService,
        private readonly EnsureStubSubscriptionAction $ensureStubSubscription
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(Company $company, Plan $plan): array
    {
        $subscription = $this->ensureStubSubscription->execute($company, $plan);
        $checkout = $this->checkoutService->createPlanCheckout($company, $plan);

        $subscription->forceFill([
            'stripe_plan' => $plan->code,
            'stripe_status' => 'checkout_pending',
            'checkout_session_id' => Arr::get($checkout, 'session_id'),
            'checkout_status' => Arr::get($checkout, 'status'),
            'checkout_url' => Arr::get($checkout, 'checkout_url'),
            'checkout_started_at' => now(),
        ])->save();

        return $checkout;
    }
}
