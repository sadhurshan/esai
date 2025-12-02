<?php

use App\Http\Middleware\EnsureEventAccess;
use App\Http\Middleware\EnsureExportAccess;
use App\Http\Middleware\EnsureNotificationAccess;
use App\Http\Middleware\EnsureSearchAccess;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

function workspaceCompany(array $planOverrides = []): Company {
    $plan = Plan::factory()->create($planOverrides);

    return Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);
}

function userForCompany(Company $company, string $role): User {
    return User::factory()->create([
        'company_id' => $company->id,
        'role' => $role,
    ]);
}

function requestForUser(User $user): Request {
    $request = Request::create('/workspace-check', 'GET');
    $request->setUserResolver(static fn () => $user);

    return $request;
}

it('allows notification inbox access when workspace permission is present', function (): void {
    $company = workspaceCompany();
    $user = userForCompany($company, 'buyer_member');

    $middleware = app(EnsureNotificationAccess::class);
    $response = $middleware->handle(requestForUser($user), static fn () => response()->json(['ok' => true]));

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
});

it('blocks notification inbox access without workspace permission', function (): void {
    $company = workspaceCompany();
    $user = userForCompany($company, 'platform_support');

    $middleware = app(EnsureNotificationAccess::class);
    $response = $middleware->handle(requestForUser($user), static fn () => response()->json(['ok' => true]));

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($response->getData(true)['message'])->toBe('Notifications require module read access.');
});

it('requires events.manage permission for event tooling', function (): void {
    $company = workspaceCompany();
    $user = userForCompany($company, 'buyer_admin');

    $middleware = app(EnsureEventAccess::class);
    $response = $middleware->handle(requestForUser($user), static fn () => response()->json(['ok' => true]));

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $blockedUser = userForCompany($company, 'buyer_member');
    $blockedResponse = $middleware->handle(requestForUser($blockedUser), static fn () => response()->json(['ok' => true]));

    expect($blockedResponse->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($blockedResponse->getData(true)['message'])->toBe('Events access requires integration permissions.');
});

it('requires orders permission when plan allows exports', function (): void {
    $company = workspaceCompany();
    $user = userForCompany($company, 'finance');

    $middleware = app(EnsureExportAccess::class);
    $response = $middleware->handle(requestForUser($user), static fn () => response()->json(['ok' => true]));

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $blockedUser = userForCompany($company, 'supplier_estimator');
    $blockedResponse = $middleware->handle(requestForUser($blockedUser), static fn () => response()->json(['ok' => true]));

    expect($blockedResponse->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($blockedResponse->getData(true)['message'])->toBe('Orders access required to run exports.');
});

it('enforces search.use permission along with plan entitlements', function (): void {
    $company = workspaceCompany(['global_search_enabled' => true]);
    $user = userForCompany($company, 'buyer_member');

    $middleware = app(EnsureSearchAccess::class);
    $response = $middleware->handle(requestForUser($user), static fn () => response()->json(['ok' => true]));

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);

    $blockedUser = userForCompany($company, 'platform_support');
    $blockedResponse = $middleware->handle(requestForUser($blockedUser), static fn () => response()->json(['ok' => true]));

    expect($blockedResponse->getStatusCode())->toBe(Response::HTTP_FORBIDDEN)
        ->and($blockedResponse->getData(true)['message'])->toBe('Search access requires read permissions.');
});
