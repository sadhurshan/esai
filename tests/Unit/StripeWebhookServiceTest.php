<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\StripeWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Stripe\Event as StripeEvent;
use Tests\TestCase;

beforeEach(function (): void {
    config()->set('services.stripe.prices', [
        'community' => null,
        'starter' => 'price_starter',
        'growth' => 'price_growth',
        'enterprise' => 'price_enterprise',
    ]);
});

it('syncs subscription records from customer subscription updated events', function (): void {
    $plan = Plan::factory()->create(['code' => 'starter']);
    $company = Company::factory()->create();

    $event = StripeEvent::constructFrom([
        'id' => 'evt_'.Str::random(12),
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => 'sub_'.Str::random(14),
                'customer' => 'cus_'.Str::random(14),
                'status' => 'active',
                'metadata' => [
                    'company_id' => $company->id,
                    'plan_code' => 'starter',
                ],
                'items' => [
                    'data' => [[
                        'price' => ['id' => 'price_starter'],
                        'quantity' => 3,
                    ]],
                ],
                'trial_end' => Carbon::now()->addDays(14)->timestamp,
                'cancel_at' => null,
            ],
        ],
    ]);

    $service = app(StripeWebhookService::class);

    $subscription = $service->handleCustomerSubscriptionUpdated($event);

    expect($subscription)->not->toBeNull()
        ->and($subscription->stripe_status)->toBe('active')
        ->and($subscription->quantity)->toBe(3)
        ->and($company->fresh()->plan_id)->toBe($plan->id)
        ->and(Subscription::count())->toBe(1);
});

it('marks subscriptions past due on failed invoices and clears on success', function (): void {
    config()->set('services.stripe.past_due_grace_days', 5);
    Carbon::setTestNow(Carbon::create(2025, 1, 1, 0, 0, 0, 'UTC'));

    $plan = Plan::factory()->create(['code' => 'starter']);
    $company = Company::factory()->create();
    $service = app(StripeWebhookService::class);

    $subscriptionEvent = StripeEvent::constructFrom([
        'id' => 'evt_sub_'.Str::random(10),
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => 'sub_test123',
                'customer' => 'cus_test123',
                'status' => 'active',
                'metadata' => [
                    'company_id' => $company->id,
                    'plan_code' => 'starter',
                ],
                'items' => [
                    'data' => [[
                        'price' => ['id' => 'price_starter'],
                        'quantity' => 1,
                    ]],
                ],
            ],
        ],
    ]);

    $service->handleCustomerSubscriptionUpdated($subscriptionEvent);

    $failedInvoice = StripeEvent::constructFrom([
        'id' => 'evt_inv_failed',
        'type' => 'invoice.payment_failed',
        'data' => [
            'object' => [
                'id' => 'in_failed',
                'subscription' => 'sub_test123',
                'customer' => 'cus_test123',
                'customer_email' => 'billing@example.com',
                'metadata' => [
                    'company_id' => $company->id,
                ],
                'lines' => [
                    'data' => [[
                        'price' => ['id' => 'price_starter'],
                        'quantity' => 1,
                    ]],
                ],
            ],
        ],
    ]);

    $service->handleInvoicePaymentFailed($failedInvoice);

    $subscription = Subscription::where('stripe_id', 'sub_test123')->firstOrFail();

    expect($subscription->stripe_status)->toBe('past_due')
        ->and($subscription->ends_at)->not->toBeNull()
        ->and($subscription->ends_at->eq(Carbon::now()->addDays(5)))->toBeTrue();

    $paidInvoice = StripeEvent::constructFrom([
        'id' => 'evt_inv_paid',
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => [
                'id' => 'in_paid',
                'subscription' => 'sub_test123',
                'customer' => 'cus_test123',
                'metadata' => [
                    'company_id' => $company->id,
                ],
                'lines' => [
                    'data' => [[
                        'price' => ['id' => 'price_starter'],
                        'quantity' => 1,
                    ]],
                ],
            ],
        ],
    ]);

    $service->handleInvoicePaymentSucceeded($paidInvoice);

    $subscription->refresh();

    expect($subscription->stripe_status)->toBe('active')
        ->and($subscription->ends_at)->toBeNull()
        ->and($company->fresh()->plan_id)->toBe($plan->id);

    Carbon::setTestNow();
});
