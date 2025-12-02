<?php

use App\Http\Middleware\EnsureBillingAccess;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

test('billing middleware denies access without permissions', function (): void {
    $company = Company::factory()->create();
    $member = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_member',
    ]);

    $request = Request::create('/api/money/fx', 'GET');
    $request->setUserResolver(static fn () => $member);

    $middleware = app(EnsureBillingAccess::class);

    $response = $middleware->handle($request, static fn (): JsonResponse => response()->json(['ok' => true]));

    expect($response->status())->toBe(403)
        ->and($response->getData(true)['message'] ?? null)->toBe('Billing access required.');
});

test('billing middleware resolves company via pivot when missing on user', function (): void {
    $company = Company::factory()->create();

    $owner = User::factory()->create([
        'company_id' => null,
        'role' => 'owner',
    ]);

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'is_default' => true,
        'last_used_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $request = Request::create('/api/money/fx', 'GET');
    $request->setUserResolver(static fn () => $owner);

    $middleware = app(EnsureBillingAccess::class);

    $response = $middleware->handle($request, static fn (): JsonResponse => response()->json(['ok' => true]));

    expect($response->status())->toBe(200);
    $owner->refresh();
    expect($owner->company_id)->toBe($company->id);
});
