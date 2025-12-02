<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('creates a Stripe billing portal session for company owners', function (): void {
    Http::fake([
        'https://api.stripe.com/v1/billing_portal/sessions' => Http::response([
            'url' => 'https://billing.stripe.com/session/test_123',
        ], 200),
    ]);

    config([
        'services.stripe.secret' => 'sk_test_123',
        'services.stripe.portal_return_url' => 'https://elements.test/app/settings/billing',
    ]);

    $plan = Plan::factory()->create();
    $company = Company::factory()->for($plan)->create([
        'stripe_id' => 'cus_123',
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'owner',
        'password' => bcrypt('Passw0rd!'),
    ]);
    $company->update(['owner_user_id' => $user->id]);

    $response = actingAs($user)->postJson('/api/billing/portal');

    $response
        ->assertOk()
        ->assertJsonPath('data.portal.url', 'https://billing.stripe.com/session/test_123');

    Http::assertSent(function (HttpRequest $request): bool {
        return $request['customer'] === 'cus_123'
            && $request['return_url'] === 'https://elements.test/app/settings/billing';
    });
});

it('blocks users without billing privileges', function (): void {
    config(['services.stripe.secret' => 'sk_test_123']);

    $company = Company::factory()->create([
        'stripe_id' => 'cus_test_blocked',
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_member',
    ]);

    actingAs($user)
        ->postJson('/api/billing/portal')
        ->assertStatus(Response::HTTP_FORBIDDEN);
});

it('returns a helpful error when the company is missing a Stripe customer id', function (): void {
    config(['services.stripe.secret' => 'sk_test_123']);

    $plan = Plan::factory()->create();
    $company = Company::factory()->for($plan)->create([
        'stripe_id' => null,
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'owner',
    ]);
    $company->update(['owner_user_id' => $user->id]);

    actingAs($user)
        ->postJson('/api/billing/portal')
        ->assertStatus(Response::HTTP_BAD_GATEWAY)
        ->assertJsonPath('errors.code', 'customer_missing');
});
