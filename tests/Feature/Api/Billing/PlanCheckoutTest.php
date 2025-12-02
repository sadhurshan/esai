<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('starts a Stripe checkout session and records the session id', function (): void {
    Http::fake([
        'https://api.stripe.com/v1/checkout/sessions' => Http::response([
            'id' => 'cs_test_123',
            'url' => 'https://checkout.stripe.com/pay/cs_test_123',
            'mode' => 'subscription',
        ], 200),
    ]);

    $plan = Plan::factory()->create([
        'code' => 'growth',
        'price_usd' => 1200,
    ]);

    config([
        'services.stripe.secret' => 'sk_test_123',
        'services.stripe.prices.growth' => 'price_123',
    ]);

    $company = Company::factory()->for($plan, 'plan')->create();
    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'owner',
        'email' => 'owner@example.com',
        'password' => bcrypt('Passw0rd!'),
    ]);
    $company->update(['owner_user_id' => $user->id]);

    $response = actingAs($user)->postJson('/api/billing/checkout', [
        'plan_code' => 'growth',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.checkout.session_id', 'cs_test_123')
        ->assertJsonPath('data.checkout.checkout_url', 'https://checkout.stripe.com/pay/cs_test_123');

    $subscription = $company->subscriptions()->first();
    expect($subscription)
        ->not->toBeNull()
        ->checkout_session_id->toBe('cs_test_123')
        ->checkout_status->toBe('requires_checkout')
        ->checkout_url->toBe('https://checkout.stripe.com/pay/cs_test_123');
});
