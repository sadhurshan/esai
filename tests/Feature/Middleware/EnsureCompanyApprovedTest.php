<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

it('blocks suspended companies with actionable messaging', function (): void {
    Route::middleware('ensure.company.approved')->get('/testing/company-approved-suspended', static function () {
        return response()->json([
            'status' => 'success',
            'message' => 'ok',
            'data' => null,
        ]);
    });

    $company = Company::factory()->create([
        'status' => 'suspended',
    ]);

    $user = User::factory()->for($company)->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    actingAs($user);

    getJson('/testing/company-approved-suspended')
        ->assertForbidden()
        ->assertJson([
            'status' => 'error',
            'message' => 'This company is currently suspended.',
            'errors' => [
                'company' => ['Account suspended. Contact support to resolve billing or compliance issues.'],
            ],
        ]);
});

it('allows active companies through the middleware', function (): void {
    Route::middleware('ensure.company.approved')->get('/testing/company-approved-active', static function () {
        return response()->json([
            'status' => 'success',
            'message' => 'ok',
            'data' => null,
        ]);
    });

    $company = Company::factory()->create([
        'status' => 'active',
    ]);

    $user = User::factory()->for($company)->create([
        'company_id' => $company->id,
    ]);

    actingAs($user);

    getJson('/testing/company-approved-active')
        ->assertOk()
        ->assertJson([
            'status' => 'success',
            'message' => 'ok',
        ]);
});
