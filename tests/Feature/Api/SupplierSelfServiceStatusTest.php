<?php

use App\Enums\CompanySupplierStatus;
use App\Enums\SupplierApplicationStatus;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierApplication;
use App\Models\SupplierDocument;
use App\Models\User;
use Illuminate\Support\Carbon;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

it('returns supplier status with pending application metadata', function () {
    Carbon::setTestNow('2024-07-01 12:00:00');

    $company = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::Pending,
        'directory_visibility' => 'private',
    ]);

    $owner = User::factory()->owner()->for($company)->create();
    $company->forceFill(['owner_user_id' => $owner->id])->save();

    SupplierApplication::factory()
        ->approved()
        ->for($company)
        ->create([
            'created_at' => now()->subMonths(2),
        ]);

    $supplier = Supplier::factory()->for($company)->create();

    $document = SupplierDocument::factory()
        ->for($supplier, 'supplier')
        ->for($company, 'company')
        ->create([
            'type' => 'iso9001',
            'expires_at' => now()->subDay(),
            'status' => 'expired',
        ]);

    $pending = SupplierApplication::factory()
        ->for($company)
        ->create([
            'status' => SupplierApplicationStatus::Pending,
            'notes' => 'Auto re-verification triggered: ISO9001 (2024-06-01).',
            'created_at' => now()->subDay(),
        ]);

    $pending->documents()->sync([$document->id]);

    actingAs($owner);

    $response = getJson('/api/me/supplier-application/status');

    $response->assertStatus(200)
        ->assertJsonPath('data.supplier_status', CompanySupplierStatus::Pending->value)
        ->assertJsonPath('data.current_application.id', $pending->id)
        ->assertJsonPath('data.current_application.status', 'pending')
        ->assertJsonPath('data.current_application.notes', $pending->notes)
        ->assertJsonPath('data.current_application.auto_reverification', true)
        ->assertJsonPath('data.current_application.submitted_at', $pending->created_at->toIso8601String())
        ->assertJsonPath('data.current_application.documents.0.id', $document->id)
        ->assertJsonPath('data.current_application.documents.0.status', 'expired')
        ->assertJsonPath('data.current_application.documents.0.type', 'iso9001');
});

it('omits application metadata when none exist', function () {
    $company = Company::factory()->create([
        'supplier_status' => CompanySupplierStatus::None,
        'directory_visibility' => 'private',
    ]);

    $owner = User::factory()->owner()->for($company)->create();
    $company->forceFill(['owner_user_id' => $owner->id])->save();

    actingAs($owner);

    $response = getJson('/api/me/supplier-application/status');

    $response->assertStatus(200)
        ->assertJsonPath('data.supplier_status', CompanySupplierStatus::None->value)
        ->assertJsonPath('data.current_application', null);
});
