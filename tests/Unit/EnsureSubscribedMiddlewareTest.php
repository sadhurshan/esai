<?php

use App\Http\Middleware\EnsureSubscribed;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use App\Models\SupplierContact;
use App\Support\ActivePersona;
use App\Support\ActivePersonaContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

test('middleware allows request when within limits and active', function (): void {

    $plan = Plan::factory()->create([
        'code' => 'starter',
        'rfqs_per_month' => 10,
        'users_max' => 5,
        'storage_gb' => 3,
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'rfqs_monthly_used' => 5,
        'storage_used_mb' => 500,
    ]);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'stripe_status' => 'active',
    ]);

    $user = User::factory()->for($company)->create();

    $request = Request::create('/api/rfqs', 'POST');
    $request->setUserResolver(static fn () => $user);

    $middleware = new EnsureSubscribed();

    $response = $middleware->handle($request, static fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('middleware allows community plan without subscription', function (): void {

    $plan = Plan::factory()->create([
        'code' => 'community',
        'price_usd' => 0,
        'rfqs_per_month' => 3,
        'users_max' => 3,
        'storage_gb' => 1,
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'rfqs_monthly_used' => 1,
        'storage_used_mb' => 100,
    ]);

    $user = User::factory()->for($company)->create();

    $request = Request::create('/api/rfqs', 'POST');
    $request->setUserResolver(static fn () => $user);

    $middleware = new EnsureSubscribed();

    $response = $middleware->handle($request, static fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('middleware allows complimentary plan with null price', function (): void {

    $plan = Plan::factory()->create([
        'code' => 'starter-free',
        'price_usd' => null,
        'rfqs_per_month' => 5,
        'users_max' => 5,
        'storage_gb' => 1,
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'rfqs_monthly_used' => 1,
        'storage_used_mb' => 100,
    ]);

    $user = User::factory()->for($company)->create();

    $request = Request::create('/api/rfqs', 'POST');
    $request->setUserResolver(static fn () => $user);

    $middleware = new EnsureSubscribed();

    $response = $middleware->handle($request, static fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('middleware blocks when rfq limit exceeded', function (): void {

    $plan = Plan::factory()->create([
        'code' => 'starter',
        'rfqs_per_month' => 10,
        'users_max' => 5,
        'storage_gb' => 3,
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'rfqs_monthly_used' => 11,
        'storage_used_mb' => 100,
    ]);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'stripe_status' => 'active',
    ]);

    $user = User::factory()->for($company)->create();

    $request = Request::create('/api/rfqs', 'POST');
    $request->setUserResolver(static fn () => $user);

    $middleware = new EnsureSubscribed();

    $response = $middleware->handle($request, static fn () => new Response('ok'));

    expect($response->getStatusCode())->toBe(402)
        ->and($response->getContent())->toContain('Upgrade required')
        ->and(json_decode($response->getContent(), true)['errors']['code'])->toBe('rfqs_per_month');
});

test('middleware allows read-only requests during billing grace period', function (): void {
    Carbon::setTestNow(now());

    $plan = Plan::factory()->create([
        'code' => 'starter',
        'price_usd' => 1200,
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'stripe_status' => 'past_due',
        'ends_at' => now()->addDays(5),
    ]);

    $user = User::factory()->for($company)->create();

    $request = Request::create('/api/rfqs', 'GET');
    $request->setUserResolver(static fn () => $user);

    $middleware = new EnsureSubscribed();

    $response = $middleware->handle($request, static fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('middleware blocks write requests during billing grace period', function (): void {
    Carbon::setTestNow(now());

    $plan = Plan::factory()->create([
        'code' => 'starter',
        'price_usd' => 1200,
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'stripe_status' => 'past_due',
        'ends_at' => now()->addDays(5),
    ]);

    $user = User::factory()->for($company)->create();

    $request = Request::create('/api/rfqs', 'POST');
    $request->setUserResolver(static fn () => $user);

    $middleware = new EnsureSubscribed();

    $response = $middleware->handle($request, static fn () => new Response('ok'));

    $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

    expect($response->getStatusCode())->toBe(402)
        ->and($payload['errors']['code'])->toBe('subscription_past_due')
        ->and($payload['errors']['read_only'])->toBeTrue();
});

test('middleware allows supplier personas without enforcing subscription checks', function (): void {
    $company = Company::factory()->create();
    $company->update(['plan_id' => null, 'plan_code' => null]);

    $user = User::factory()->for($company)->create();
    $supplier = Supplier::factory()->for($company)->create();

    SupplierContact::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'user_id' => $user->id,
    ]);

    $persona = ActivePersona::fromArray([
        'key' => 'supplier-'.$supplier->id,
        'type' => ActivePersona::TYPE_SUPPLIER,
        'company_id' => $company->id,
        'company_name' => $company->name,
        'supplier_id' => $supplier->id,
        'supplier_name' => $supplier->name,
    ]);

    expect($persona)->not->toBeNull();

    ActivePersonaContext::set($persona);

    try {
        $request = Request::create('/api/money/settings', 'GET');
        $request->setUserResolver(static fn () => $user);

        $middleware = new EnsureSubscribed();

        $response = $middleware->handle($request, static fn () => new Response('ok'));

        expect($response->getContent())->toBe('ok');
    } finally {
        ActivePersonaContext::clear();
    }
});
