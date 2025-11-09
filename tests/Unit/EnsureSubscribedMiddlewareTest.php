<?php

use App\Http\Middleware\EnsureSubscribed;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('middleware allows request when within limits and active', function (): void {

    Plan::factory()->create([
        'code' => 'starter',
        'rfqs_per_month' => 10,
        'users_max' => 5,
        'storage_gb' => 3,
    ]);

    $company = Company::factory()->create([
        'plan_code' => 'starter',
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

test('middleware blocks when rfq limit exceeded', function (): void {

    Plan::factory()->create([
        'code' => 'starter',
        'rfqs_per_month' => 10,
        'users_max' => 5,
        'storage_gb' => 3,
    ]);

    $company = Company::factory()->create([
        'plan_code' => 'starter',
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
