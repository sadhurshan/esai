<?php

use App\Http\Middleware\EnsureAnalyticsAccess;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

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

    $middleware = new EnsureAnalyticsAccess();

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

    $middleware = new EnsureAnalyticsAccess();

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

    $middleware = new EnsureAnalyticsAccess();

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

    $middleware = new EnsureAnalyticsAccess();

    $response = $middleware->handle($request, static fn () => new Response('ok'));

    expect($response->getContent())->toBe('ok');
});
