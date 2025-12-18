<?php

use App\Enums\CompanyStatus;
use App\Http\Middleware\ResolveCompanyContext;
use App\Models\Company;
use App\Models\User;
use Illuminate\Session\Middleware\StartSession;
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

it('blocks pending verification companies with the standard envelope', function (): void {
    Route::middleware('ensure.company.approved')->get('/testing/company-approved-pending', static function () {
        return response()->json([
            'status' => 'success',
            'message' => 'ok',
            'data' => null,
        ]);
    });

    $company = Company::factory()->create([
        'status' => CompanyStatus::PendingVerification,
    ]);

    $user = User::factory()->for($company)->create([
        'company_id' => $company->id,
    ]);

    actingAs($user);

    getJson('/testing/company-approved-pending')
        ->assertForbidden()
        ->assertJson([
            'status' => 'error',
            'message' => 'Your company must be approved by Elements Supply operations before performing this action.',
            'errors' => [
                'company' => ['Company approval pending. A platform admin must verify your documents first.'],
            ],
        ]);
});

it('allows supplier personas to bypass the approval guard', function (): void {
    Route::middleware([StartSession::class, ResolveCompanyContext::class, 'ensure.company.approved'])->get('/testing/company-approved-supplier-persona', static fn () => response()->json(['status' => 'success']));

    $buyerCompany = createSubscribedCompany([
        'status' => CompanyStatus::Pending->value,
    ]);

    $supplierContext = createSupplierPersonaForBuyer($buyerCompany);

    actingAs($supplierContext['user']);

    $this->withHeaders([
        'X-Active-Persona' => $supplierContext['persona']['key'],
    ])->getJson('/testing/company-approved-supplier-persona')
        ->assertOk()
        ->assertJsonPath('status', 'success');
});
