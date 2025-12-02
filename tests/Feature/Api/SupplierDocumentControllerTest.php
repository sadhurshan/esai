<?php

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Models\Company;
use App\Models\Document;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('allows supplier admins to upload and list compliance documents', function (): void {
    config(['documents.disk' => 'public']);
    config(['filesystems.disks.public.url' => 'https://files.test/storage']);
    Storage::fake('public');

    $owner = User::factory()->create(['role' => 'owner']);

    $company = Company::factory()->create([
        'status' => CompanyStatus::Active,
        'supplier_status' => CompanySupplierStatus::Approved,
        'owner_user_id' => $owner->id,
    ]);

    $owner->forceFill(['company_id' => $company->id])->save();

    $supplierAdmin = User::factory()->create([
        'role' => 'supplier_admin',
        'company_id' => $company->id,
    ]);

    Supplier::factory()->create([
        'company_id' => $company->id,
        'status' => 'approved',
    ]);

    DB::table('company_user')->insert([
        [
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => $owner->role,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'company_id' => $company->id,
            'user_id' => $supplierAdmin->id,
            'role' => $supplierAdmin->role,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    actingAs($supplierAdmin);

    $issuedAt = now()->subYear()->toDateString();
    $expiresAt = now()->addMonths(6)->toDateString();

    $response = $this->postJson('/api/me/supplier-documents', [
        'type' => 'iso9001',
        'issued_at' => $issuedAt,
        'expires_at' => $expiresAt,
        'document' => UploadedFile::fake()->create('iso9001.pdf', 256, 'application/pdf'),
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.type', 'iso9001')
        ->assertJsonPath('data.status', 'valid')
        ->assertJsonPath('data.download_url', fn (?string $url) => is_string($url) && $url !== '')
        ->assertJsonPath('message', 'Supplier document uploaded.');

    expect(SupplierDocument::query()->count())->toBe(1);

    $document = SupplierDocument::first();

    expect($document)
        ->not->toBeNull()
        ->and($document->company_id)->toBe($company->id)
        ->and($document->document_id)->not->toBeNull()
        ->and($document->status)->toBe('valid')
        ->and($document->issued_at?->toDateString())->toBe($issuedAt)
        ->and($document->expires_at?->toDateString())->toBe($expiresAt);

    Storage::disk('public')->assertExists($document->path);

    $linkedDocument = Document::query()->find($document->document_id);

    expect($linkedDocument)->not->toBeNull();
    expect($linkedDocument->documentable_id)->toBe($document->supplier_id);
    expect($linkedDocument->kind)->toBe('certificate');

    $indexResponse = $this->getJson('/api/me/supplier-documents');

    $indexResponse
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $document->id);
});

it('blocks unauthorized roles from uploading supplier documents', function (): void {
    config(['documents.disk' => 'public']);
    Storage::fake('public');
    $owner = User::factory()->create(['role' => 'owner']);

    $company = Company::factory()->create([
        'status' => CompanyStatus::Active,
        'supplier_status' => CompanySupplierStatus::Approved,
        'owner_user_id' => $owner->id,
    ]);

    $owner->forceFill(['company_id' => $company->id])->save();

    Supplier::factory()->create([
        'company_id' => $company->id,
        'status' => 'approved',
    ]);

    $buyerUser = User::factory()->create([
        'role' => 'buyer_requester',
        'company_id' => $company->id,
    ]);

    DB::table('company_user')->insert([
        [
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => $owner->role,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'company_id' => $company->id,
            'user_id' => $buyerUser->id,
            'role' => $buyerUser->role,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    actingAs($buyerUser);

    $response = $this->postJson('/api/me/supplier-documents', [
        'type' => 'iso9001',
        'document' => UploadedFile::fake()->create('iso.pdf', 128, 'application/pdf'),
    ]);

    $response->assertForbidden();

    expect(SupplierDocument::query()->count())->toBe(0);
});

it('auto provisions a supplier profile when uploading documents before approval', function (): void {
    config(['documents.disk' => 'public']);
    Storage::fake('public');

    $owner = User::factory()->create(['role' => 'owner']);

    $company = Company::factory()->create([
        'status' => CompanyStatus::Active,
        'supplier_status' => CompanySupplierStatus::None,
        'owner_user_id' => $owner->id,
    ]);

    $owner->forceFill(['company_id' => $company->id])->save();

    DB::table('company_user')->insert([
        [
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => $owner->role,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    actingAs($owner);

    $response = $this->postJson('/api/me/supplier-documents', [
        'type' => 'iso9001',
        'document' => UploadedFile::fake()->create('cert.pdf', 64, 'application/pdf'),
    ]);

    $response->assertOk();

    $supplier = Supplier::query()->where('company_id', $company->id)->first();

    expect($supplier)
        ->not->toBeNull()
        ->and($supplier->status)->toBe('pending');

    expect(SupplierDocument::query()->count())->toBe(1);
});
