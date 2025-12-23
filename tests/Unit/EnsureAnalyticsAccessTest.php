<?php

use App\Http\Middleware\EnsureAnalyticsAccess;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ActivePersona;
use App\Support\ActivePersonaContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    ActivePersonaContext::set(null);
});

test('community plan can access analytics endpoints', function (): void {
    $plan = Plan::factory()->create([
        'code' => 'community',
        'analytics_enabled' => false,
        'price_usd' => 0,
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $user = User::factory()->for($company)->create();

    $request = Request::create('/api/dashboard/metrics', 'GET');
    $request->setUserResolver(static fn () => $user);

    $middleware = app(EnsureAnalyticsAccess::class);

    $response = $middleware->handle($request, static fn () => new Response('ok'));

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($response->getContent())->toBe('ok');
});

test('paid plans without analytics require upgrade', function (): void {
    $plan = Plan::factory()->create([
        'code' => 'starter',
        'analytics_enabled' => false,
        'price_usd' => 2400,
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $user = User::factory()->for($company)->create();

    $request = Request::create('/api/dashboard/metrics', 'GET');
    $request->setUserResolver(static fn () => $user);

    $middleware = app(EnsureAnalyticsAccess::class);

    $response = $middleware->handle($request, static fn () => new Response('ok'));

    expect($response->getStatusCode())->toBe(Response::HTTP_PAYMENT_REQUIRED)
        ->and($response->getContent())->toContain('Current plan does not include analytics features.');
});

test('companies without a plan are blocked', function (): void {
    $company = Company::factory()->create([
        'plan_id' => null,
        'plan_code' => null,
    ]);

    $user = User::factory()->for($company)->create();

    $request = Request::create('/api/dashboard/metrics', 'GET');
    $request->setUserResolver(static fn () => $user);

    $middleware = app(EnsureAnalyticsAccess::class);

    $response = $middleware->handle($request, static fn () => new Response('ok'));

    expect($response->getStatusCode())->toBe(Response::HTTP_PAYMENT_REQUIRED)
        ->and($response->getContent())->toContain('Upgrade required');
});

test('analytics-enabled plans can access analytics endpoints', function (): void {
    $plan = Plan::factory()->create([
        'code' => 'growth',
        'analytics_enabled' => true,
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $user = User::factory()->for($company)->create();

    $request = Request::create('/api/dashboard/metrics', 'GET');
    $request->setUserResolver(static fn () => $user);

    $middleware = app(EnsureAnalyticsAccess::class);

    $response = $middleware->handle($request, static fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('finance role can access analytics endpoints', function (): void {
    $plan = Plan::factory()->create([
        'code' => 'pro',
        'analytics_enabled' => true,
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $user = User::factory()->for($company)->create([
        'role' => 'finance',
    ]);

    $request = Request::create('/api/dashboard/metrics', 'GET');
    $request->setUserResolver(static fn () => $user);

    $middleware = app(EnsureAnalyticsAccess::class);

    $response = $middleware->handle($request, static fn () => new Response('ok'));

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($response->getContent())->toBe('ok');
});

test('supplier roles cannot access analytics endpoints', function (): void {
    $plan = Plan::factory()->create([
        'code' => 'growth',
        'analytics_enabled' => true,
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $user = User::factory()->for($company)->create([
        'role' => 'supplier_admin',
    ]);

    $request = Request::create('/api/dashboard/metrics', 'GET');
    $request->setUserResolver(static fn () => $user);

    $middleware = app(EnsureAnalyticsAccess::class);

    $response = $middleware->handle($request, static fn () => new Response('ok'));

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($response->getContent())->toContain('Analytics role required.');
});

test('buyer persona owner uses persona company plan for analytics gating', function (): void {
    $defaultPlan = Plan::factory()->create([
        'code' => 'starter',
        'analytics_enabled' => false,
    ]);

    $personaPlan = Plan::factory()->create([
        'code' => 'growth',
        'analytics_enabled' => true,
    ]);

    $defaultCompany = Company::factory()->create([
        'plan_id' => $defaultPlan->id,
        'plan_code' => $defaultPlan->code,
    ]);

    $personaCompany = Company::factory()->create([
        'plan_id' => $personaPlan->id,
        'plan_code' => $personaPlan->code,
    ]);

    $user = User::factory()->for($defaultCompany)->create([
        'role' => 'owner',
    ]);

    $user->companies()->attach($personaCompany->id, ['role' => 'owner']);

    $persona = ActivePersona::fromArray([
        'key' => sprintf('buyer:%d', $personaCompany->id),
        'type' => ActivePersona::TYPE_BUYER,
        'company_id' => $personaCompany->id,
        'company_name' => $personaCompany->name,
        'role' => 'owner',
    ]);

    ActivePersonaContext::set($persona);

    $request = Request::create('/api/dashboard/metrics', 'GET');
    $request->setUserResolver(static fn () => $user);

    $middleware = app(EnsureAnalyticsAccess::class);

    $response = $middleware->handle($request, static fn () => new Response('ok'));

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($response->getContent())->toBe('ok');

    ActivePersonaContext::set(null);
});

test('supplier persona owner requires buyer growth plan for analytics', function (): void {
    $buyerPlan = Plan::factory()->create([
        'code' => 'growth',
        'analytics_enabled' => true,
    ]);

    $supplierPlan = Plan::factory()->create([
        'code' => 'supplier_growth',
        'analytics_enabled' => true,
    ]);

    $buyerCompany = Company::factory()->create([
        'plan_id' => $buyerPlan->id,
        'plan_code' => $buyerPlan->code,
    ]);

    $supplierCompany = Company::factory()->create([
        'plan_id' => $supplierPlan->id,
        'plan_code' => $supplierPlan->code,
    ]);

    $supplier = Supplier::factory()->for($supplierCompany, 'company')->create();

    $user = User::factory()->for($supplierCompany)->create([
        'role' => 'owner',
    ]);

    $persona = ActivePersona::fromArray([
        'key' => sprintf('supplier:%d:%d', $buyerCompany->id, $supplier->id),
        'type' => ActivePersona::TYPE_SUPPLIER,
        'company_id' => $buyerCompany->id,
        'company_name' => $buyerCompany->name,
        'role' => 'owner',
        'supplier_id' => $supplier->id,
        'supplier_company_id' => $supplierCompany->id,
        'supplier_company_name' => $supplierCompany->name,
    ]);

    ActivePersonaContext::set($persona);

    $request = Request::create('/api/dashboard/metrics', 'GET');
    $request->setUserResolver(static fn () => $user);

    $middleware = app(EnsureAnalyticsAccess::class);

    $response = $middleware->handle($request, static fn () => new Response('ok'));

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($response->getContent())->toBe('ok');

    ActivePersonaContext::set(null);
});
