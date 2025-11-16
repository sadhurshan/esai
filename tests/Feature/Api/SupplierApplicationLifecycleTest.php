<?php

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Enums\SupplierApplicationStatus;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierApplication;
use App\Models\User;
use App\Notifications\SupplierApplicationApproved;
use App\Notifications\SupplierApplicationSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('allows a company owner to apply for supplier status and a platform admin to approve it', function (): void {
    Notification::fake();

    $owner = User::factory()->create([
        'role' => 'owner',
    ]);

    $company = Company::factory()->create([
        'status' => CompanyStatus::Active,
        'supplier_status' => CompanySupplierStatus::None,
        'is_verified' => false,
        'verified_at' => null,
        'verified_by' => null,
        'owner_user_id' => $owner->id,
    ]);

    $owner->forceFill(['company_id' => $company->id])->save();

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'role' => $owner->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $admin = User::factory()->create([
        'role' => 'platform_super',
        'company_id' => null,
    ]);

    actingAs($owner);

    $payload = [
        'capabilities' => [
            'methods' => ['cnc_machining'],
            'materials' => ['aluminum'],
            'industries' => ['aerospace'],
        ],
        'address' => '120 Machinist Way',
        'country' => 'US',
        'city' => 'Seattle',
        'moq' => 25,
        'lead_time_days' => 14,
        'certifications' => ['iso9001'],
        'facilities' => 'Anodizing line and 5-axis machining center.',
        'website' => 'https://supplier.example',
        'contact' => [
            'name' => 'Taylor Owner',
            'email' => 'owner@supplier.example',
            'phone' => '+1-555-000-1111',
        ],
        'notes' => 'Ready to support rush aerospace projects.',
    ];

    $submitResponse = $this->postJson('/api/me/apply-supplier', $payload);

    $submitResponse
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', SupplierApplicationStatus::Pending->value);

    $application = SupplierApplication::query()->where('company_id', $company->id)->firstOrFail();

    Notification::assertSentTo($admin, SupplierApplicationSubmitted::class);

    $company->refresh();

    expect($application->status)->toBe(SupplierApplicationStatus::Pending)
        ->and($company->supplier_status)->toBe(CompanySupplierStatus::Pending)
        ->and($company->supplier_profile_completed_at)->not->toBeNull()
        ->and($company->directory_visibility)->toBe('private');

    actingAs($admin);

    $approveResponse = $this->postJson("/api/admin/supplier-applications/{$application->id}/approve", [
        'notes' => 'Welcome aboard.',
    ]);

    $approveResponse
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', SupplierApplicationStatus::Approved->value);

    $company->refresh();
    $application->refresh();

    Notification::assertSentTo($owner, SupplierApplicationApproved::class);

    expect($application->status)->toBe(SupplierApplicationStatus::Approved)
        ->and($application->reviewed_by)->toBe($admin->id)
        ->and($company->supplier_status)->toBe(CompanySupplierStatus::Approved)
        ->and($company->is_verified)->toBeTrue()
        ->and($company->verified_by)->toBe($admin->id)
        ->and($company->supplierProfile)->not->toBeNull();

    $supplier = Supplier::query()->where('company_id', $company->id)->firstOrFail();

    expect($supplier->status)->toBe('approved')
        ->and($supplier->moq)->toBe(25)
        ->and($supplier->lead_time_days)->toBe(14)
        ->and($supplier->country)->toBe('US')
        ->and($supplier->city)->toBe('Seattle')
        ->and($supplier->capabilities['methods'])->toContain('cnc_machining')
        ->and($supplier->verified_at)->not->toBeNull();
});

it('blocks non-owner users from submitting supplier applications', function (): void {
    $owner = User::factory()->create([
        'role' => 'owner',
    ]);

    $company = Company::factory()->create([
        'status' => CompanyStatus::Active,
        'supplier_status' => CompanySupplierStatus::None,
        'owner_user_id' => $owner->id,
    ]);

    $owner->forceFill(['company_id' => $company->id])->save();

    $buyerAdmin = User::factory()->create([
        'role' => 'buyer_admin',
        'company_id' => $company->id,
    ]);

    DB::table('company_user')->insert([
        ['company_id' => $company->id, 'user_id' => $owner->id, 'role' => $owner->role, 'created_at' => now(), 'updated_at' => now()],
        ['company_id' => $company->id, 'user_id' => $buyerAdmin->id, 'role' => $buyerAdmin->role, 'created_at' => now(), 'updated_at' => now()],
    ]);

    actingAs($buyerAdmin);

    $response = $this->postJson('/api/me/apply-supplier', [
        'capabilities' => ['methods' => ['cnc_machining']],
        'address' => '456 Industrial Ave',
    ]);

    $response->assertForbidden();

    expect(SupplierApplication::query()->count())->toBe(0)
        ->and($company->fresh()->supplier_status)->toBe(CompanySupplierStatus::None);
});

it('blocks supplier application if the company is still pending approval', function (): void {
    $owner = User::factory()->create([
        'role' => 'owner',
    ]);

    $company = Company::factory()->create([
        'status' => CompanyStatus::PendingVerification,
        'supplier_status' => CompanySupplierStatus::None,
        'owner_user_id' => $owner->id,
    ]);

    $owner->forceFill(['company_id' => $company->id])->save();

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'role' => $owner->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($owner);

    $response = $this->postJson('/api/me/apply-supplier', [
        'capabilities' => [
            'methods' => ['cnc_machining'],
        ],
        'address' => '789 Pending Way',
        'country' => 'US',
        'city' => 'Austin',
        'moq' => 5,
        'lead_time_days' => 7,
        'contact' => [
            'name' => 'Pending Owner',
            'email' => 'pending@example.com',
        ],
    ]);

    $response->assertStatus(403)->assertJsonPath('errors.company.0', 'Company approval pending. A platform admin must verify your documents first.');

    expect(SupplierApplication::query()->count())->toBe(0)
        ->and($company->fresh()->supplier_status)->toBe(CompanySupplierStatus::None);
});
