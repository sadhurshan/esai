<?php

use App\Http\Middleware\BuyerAdminOnly;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

test('buyer admin middleware allows users with tenant settings permission', function (): void {
    $company = Company::factory()->create();

    $owner = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'owner',
    ]);

    $request = Request::create('/test', 'GET');
    $request->setUserResolver(static fn () => $owner);

    $middleware = app(BuyerAdminOnly::class);

    $response = $middleware->handle($request, static fn (): JsonResponse => response()->json(['ok' => true]));

    expect($response->status())->toBe(200);
});

test('buyer admin middleware blocks users without tenant settings permission', function (): void {
    $company = Company::factory()->create();

    $member = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_member',
    ]);

    $request = Request::create('/test', 'GET');
    $request->setUserResolver(static fn () => $member);

    $middleware = app(BuyerAdminOnly::class);

    $response = $middleware->handle($request, static fn () => response()->json(['ok' => true]));

    expect($response->status())->toBe(403);
    expect($response->getData(true)['message'] ?? null)->toBe('Tenant admin permission required.');
});
