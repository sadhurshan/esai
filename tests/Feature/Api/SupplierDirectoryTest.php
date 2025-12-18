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

it('shows public suppliers across tenants for authenticated buyers', function (): void {
    $viewerCompany = Company::factory()->create();
    $publicCompany = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'directory_visibility' => 'public',
        'supplier_profile_completed_at' => now(),
    ]);

    $publicSupplier = Supplier::factory()->for($publicCompany)->create([
        'status' => 'approved',
    ]);

    $user = User::factory()->create([
        'company_id' => $viewerCompany->id,
        'role' => 'buyer_admin',
    ]);

    actingAs($user);

    $response = $this->getJson('/api/suppliers');

    $response->assertOk()->assertJsonPath('status', 'success');

    $ids = collect($response->json('data.items'))->pluck('id');

    expect($ids)->toContain($publicSupplier->id);
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

it('sorts suppliers by match score by default', function (): void {
    $highCompany = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'directory_visibility' => 'public',
        'supplier_profile_completed_at' => now(),
        'is_verified' => true,
    ]);

    $lowCompany = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'directory_visibility' => 'public',
        'supplier_profile_completed_at' => now(),
        'is_verified' => false,
    ]);

    $highSupplier = Supplier::factory()->for($highCompany)->create([
        'status' => 'approved',
        'rating_avg' => 4.9,
        'lead_time_days' => 7,
        'verified_at' => now(),
    ]);

    $lowSupplier = Supplier::factory()->for($lowCompany)->create([
        'status' => 'approved',
        'rating_avg' => 1.2,
        'lead_time_days' => 40,
        'verified_at' => null,
    ]);

    $response = $this->getJson('/api/suppliers?sort=match_score');

    $response->assertOk();

    $ids = collect($response->json('data.items'))->pluck('id');

    expect($ids->first())->toBe($highSupplier->id)
        ->and($ids)->toContain($lowSupplier->id);
});

it('sorts suppliers by distance when origin coordinates are provided', function (): void {
    $nearCompany = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'directory_visibility' => 'public',
        'supplier_profile_completed_at' => now(),
    ]);

    $farCompany = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'directory_visibility' => 'public',
        'supplier_profile_completed_at' => now(),
    ]);

    $nearSupplier = Supplier::factory()->for($nearCompany)->create([
        'status' => 'approved',
        'geo_lat' => 37.7749,
        'geo_lng' => -122.4194,
    ]);

    $farSupplier = Supplier::factory()->for($farCompany)->create([
        'status' => 'approved',
        'geo_lat' => 40.7128,
        'geo_lng' => -74.0060,
    ]);

    $response = $this->getJson('/api/suppliers?sort=distance&origin_lat=37.7749&origin_lng=-122.4194');

    $response->assertOk();

    $ids = collect($response->json('data.items'))->pluck('id');

    expect($ids->first())->toBe($nearSupplier->id)
        ->and($ids)->toContain($farSupplier->id);
});

it('sorts suppliers by price band with MOQ fallback', function (): void {
    $budgetCompany = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'directory_visibility' => 'public',
        'supplier_profile_completed_at' => now(),
    ]);

    $premiumCompany = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'directory_visibility' => 'public',
        'supplier_profile_completed_at' => now(),
    ]);

    $fallbackCompany = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'directory_visibility' => 'public',
        'supplier_profile_completed_at' => now(),
    ]);

    $budgetSupplier = Supplier::factory()->for($budgetCompany)->create([
        'status' => 'approved',
        'capabilities' => ['price_band' => 'budget'],
    ]);

    $premiumSupplier = Supplier::factory()->for($premiumCompany)->create([
        'status' => 'approved',
        'capabilities' => ['price_band' => 'premium'],
    ]);

    $fallbackSupplier = Supplier::factory()->for($fallbackCompany)->create([
        'status' => 'approved',
        'moq' => 25,
        'capabilities' => [],
    ]);

    $response = $this->getJson('/api/suppliers?sort=price_band');

    $response->assertOk();

    $ids = collect($response->json('data.items'))->pluck('id');
    $positions = $ids->flip();

    expect($positions[$budgetSupplier->id])->toBeLessThan($positions[$premiumSupplier->id])
        ->and($positions[$fallbackSupplier->id])->toBeLessThan($positions[$premiumSupplier->id]);
});
