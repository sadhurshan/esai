<?php

use App\Enums\CompanySupplierStatus;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('lists only approved public suppliers with completed profiles', function (): void {
    $listedCompany = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'directory_visibility' => 'public',
        'supplier_profile_completed_at' => now(),
    ]);

    $listedSupplier = Supplier::factory()->for($listedCompany)->create([
        'status' => 'approved',
    ]);

    $privateCompany = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'directory_visibility' => 'private',
        'supplier_profile_completed_at' => now(),
    ]);
    Supplier::factory()->for($privateCompany)->create([
        'status' => 'approved',
    ]);

    $pendingCompany = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Pending->value,
        'directory_visibility' => 'public',
        'supplier_profile_completed_at' => now(),
    ]);
    Supplier::factory()->for($pendingCompany)->create([
        'status' => 'approved',
    ]);

    $incompleteCompany = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'directory_visibility' => 'public',
        'supplier_profile_completed_at' => null,
    ]);
    Supplier::factory()->for($incompleteCompany)->create([
        'status' => 'approved',
    ]);

    $response = $this->getJson('/api/suppliers');

    $response->assertOk()->assertJsonPath('status', 'success');

    $ids = collect($response->json('data.items'))->pluck('id');

    expect($ids)->toContain($listedSupplier->id)
        ->and($ids)->toHaveCount(1);
});

it('blocks visibility toggle when supplier is not approved', function (): void {
    $company = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Pending->value,
        'directory_visibility' => 'private',
        'supplier_profile_completed_at' => now(),
    ]);

    $user = User::factory()->create([
        'role' => 'owner',
        'company_id' => $company->id,
    ]);

    actingAs($user);

    $response = $this->putJson('/api/me/supplier/visibility', [
        'visibility' => 'public',
    ]);

    $response->assertForbidden();

    expect($company->fresh()->directory_visibility)->toBe('private');
});

it('allows approved supplier owners to toggle visibility', function (): void {
    $company = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'directory_visibility' => 'private',
        'supplier_profile_completed_at' => now(),
    ]);

    $user = User::factory()->create([
        'role' => 'owner',
        'company_id' => $company->id,
    ]);

    actingAs($user);

    $response = $this->putJson('/api/me/supplier/visibility', [
        'visibility' => 'public',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.directory_visibility', 'public');

    expect($company->fresh()->directory_visibility)->toBe('public');
});

it('requires a completed profile before listing publicly', function (): void {
    $company = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'directory_visibility' => 'private',
        'supplier_profile_completed_at' => null,
    ]);

    $user = User::factory()->create([
        'role' => 'owner',
        'company_id' => $company->id,
    ]);

    actingAs($user);

    $response = $this->putJson('/api/me/supplier/visibility', [
        'visibility' => 'public',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error');

    expect($company->fresh()->directory_visibility)->toBe('private');
});
