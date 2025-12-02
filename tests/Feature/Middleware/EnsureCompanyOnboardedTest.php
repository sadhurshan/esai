<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

it('blocks users with no company memberships and surfaces guidance', function (): void {
    Route::middleware('ensure.company.onboarded')->get('/testing/company-onboarded-guard-missing', static function () {
        return response()->json([
            'status' => 'success',
            'message' => 'ok',
            'data' => null,
        ]);
    });

    $user = User::factory()->create([
        'company_id' => null,
    ]);

    actingAs($user);

    getJson('/testing/company-onboarded-guard-missing')
        ->assertForbidden()
        ->assertJson([
            'status' => 'error',
            'message' => 'No active company membership.',
            'errors' => [
                'company' => ['You are not assigned to any company. Request a new invitation or contact your administrator.'],
            ],
        ]);
});

it('reattaches the default membership when the pivot still exists', function (): void {
    Route::middleware('ensure.company.onboarded')->get('/testing/company-onboarded-guard-reattach', static function () {
        return response()->json([
            'status' => 'success',
            'message' => 'ok',
            'data' => null,
        ]);
    });

    $company = Company::factory()->create();
    $user = User::factory()->create([
        'company_id' => null,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'buyer_admin',
        'is_default' => true,
        'last_used_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($user);

    getJson('/testing/company-onboarded-guard-reattach')
        ->assertOk()
        ->assertJson([
            'status' => 'success',
            'message' => 'ok',
        ]);

    $user->refresh();

    expect($user->company_id)->toBe($company->id);
});

it('allows owners to pass through while onboarding incomplete', function (): void {
    Route::middleware('ensure.company.onboarded')->get('/testing/company-onboarded-owner', static fn () => response()->json(['status' => 'success']));

    $company = Company::factory()->create([
        'primary_contact_name' => null,
    ]);

    $owner = User::factory()->owner()->create([
        'company_id' => $company->id,
    ]);

    actingAs($owner);

    getJson('/testing/company-onboarded-owner')
        ->assertOk();
});

it('allows buyer admins to pass through while onboarding incomplete', function (): void {
    Route::middleware('ensure.company.onboarded')->get('/testing/company-onboarded-buyer-admin', static fn () => response()->json(['status' => 'success']));

    $company = Company::factory()->create([
        'primary_contact_name' => null,
    ]);

    $admin = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    actingAs($admin);

    getJson('/testing/company-onboarded-buyer-admin')
        ->assertOk();
});

it('blocks non-admin roles when onboarding incomplete', function (): void {
    Route::middleware('ensure.company.onboarded')->get('/testing/company-onboarded-block', static fn () => response()->json(['status' => 'success']));

    $company = Company::factory()->create([
        'primary_contact_name' => null,
    ]);

    $member = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_member',
    ]);

    actingAs($member);

    getJson('/testing/company-onboarded-block')
        ->assertForbidden()
        ->assertJsonPath('message', 'Company onboarding incomplete.');
});
