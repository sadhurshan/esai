<?php

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Enums\DocumentCategory;
use App\Enums\DocumentKind;
use App\Enums\SupplierApplicationStatus;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Document;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\SupplierApplication;
use App\Models\User;
use App\Notifications\SupplierApplicationApproved;
use App\Notifications\SupplierApplicationSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

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

    $supplier = Supplier::factory()->create([
        'company_id' => $company->id,
        'status' => 'pending',
    ]);

    $linkedDocument = Document::factory()->create([
        'company_id' => $company->id,
        'documentable_type' => Supplier::class,
        'documentable_id' => $supplier->id,
        'kind' => DocumentKind::Certificate->value,
        'category' => DocumentCategory::Qa->value,
        'visibility' => 'company',
        'expires_at' => now()->addYear(),
        'path' => 'supplier-documents/'.$company->id.'/doc.pdf',
        'filename' => 'doc.pdf',
        'mime' => 'application/pdf',
        'size_bytes' => 2048,
    ]);

    $document = SupplierDocument::create([
        'supplier_id' => $supplier->id,
        'company_id' => $company->id,
        'document_id' => $linkedDocument->id,
        'type' => 'iso9001',
        'path' => $linkedDocument->path,
        'mime' => $linkedDocument->mime,
        'size_bytes' => $linkedDocument->size_bytes,
        'issued_at' => now()->subYear(),
        'expires_at' => now()->addYear(),
        'status' => 'valid',
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
        'documents' => [$document->id],
    ];

    $submitResponse = $this->postJson('/api/me/apply-supplier', $payload);

    $submitResponse
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', SupplierApplicationStatus::Pending->value)
        ->assertJsonPath('data.documents.0.id', $document->id);

    $application = SupplierApplication::query()->where('company_id', $company->id)->firstOrFail();

    $application->load('documents');

    expect($application->documents)->toHaveCount(1)
        ->and($application->documents->first()->id)->toBe($document->id);

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

it('rejects supplier applications referencing documents from another company', function (): void {
    $owner = User::factory()->create([
        'role' => 'owner',
    ]);

    $company = Company::factory()->create([
        'status' => CompanyStatus::Active,
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

    $otherCompany = Company::factory()->create([
        'status' => CompanyStatus::Active,
        'supplier_status' => CompanySupplierStatus::Approved,
    ]);

    $foreignSupplier = Supplier::factory()->create([
        'company_id' => $otherCompany->id,
        'status' => 'approved',
    ]);

    $foreignLinkedDocument = Document::factory()->create([
        'company_id' => $otherCompany->id,
        'documentable_type' => Supplier::class,
        'documentable_id' => $foreignSupplier->id,
        'kind' => DocumentKind::Certificate->value,
        'category' => DocumentCategory::Qa->value,
        'visibility' => 'company',
        'expires_at' => now()->addMonths(6),
        'path' => 'supplier-documents/'.$otherCompany->id.'/doc.pdf',
        'filename' => 'doc.pdf',
        'mime' => 'application/pdf',
        'size_bytes' => 512,
    ]);

    $foreignDocument = SupplierDocument::create([
        'supplier_id' => $foreignSupplier->id,
        'company_id' => $otherCompany->id,
        'document_id' => $foreignLinkedDocument->id,
        'type' => 'iso9001',
        'path' => $foreignLinkedDocument->path,
        'mime' => $foreignLinkedDocument->mime,
        'size_bytes' => $foreignLinkedDocument->size_bytes,
        'status' => 'valid',
    ]);

    actingAs($owner);

    $response = $this->postJson('/api/me/apply-supplier', [
        'capabilities' => [
            'methods' => ['cnc_machining'],
        ],
        'address' => '123 Way',
        'documents' => [$foreignDocument->id],
        'contact' => [
            'email' => 'owner@example.com',
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.documents.0', 'Provided documents must exist, belong to your company, and remain active.');

    expect(SupplierApplication::query()->count())->toBe(0);
});

it('rejects supplier applications referencing expired documents', function (): void {
    $owner = User::factory()->create([
        'role' => 'owner',
    ]);

    $company = Company::factory()->create([
        'status' => CompanyStatus::Active,
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

    $supplier = Supplier::factory()->create([
        'company_id' => $company->id,
        'status' => 'approved',
    ]);

    $expiredLinkedDocument = Document::factory()->create([
        'company_id' => $company->id,
        'documentable_type' => Supplier::class,
        'documentable_id' => $supplier->id,
        'kind' => DocumentKind::Certificate->value,
        'category' => DocumentCategory::Qa->value,
        'visibility' => 'company',
        'expires_at' => now()->subMonth(),
        'path' => 'supplier-documents/'.$company->id.'/expired.pdf',
        'filename' => 'expired.pdf',
        'mime' => 'application/pdf',
        'size_bytes' => 1024,
    ]);

    $expiredDocument = SupplierDocument::create([
        'supplier_id' => $supplier->id,
        'company_id' => $company->id,
        'document_id' => $expiredLinkedDocument->id,
        'type' => 'iso9001',
        'path' => $expiredLinkedDocument->path,
        'mime' => $expiredLinkedDocument->mime,
        'size_bytes' => $expiredLinkedDocument->size_bytes,
        'issued_at' => now()->subYears(2),
        'expires_at' => now()->subMonth(),
        'status' => 'expired',
    ]);

    actingAs($owner);

    $response = $this->postJson('/api/me/apply-supplier', [
        'capabilities' => [
            'methods' => ['cnc_machining'],
        ],
        'address' => 'Expired Way',
        'documents' => [$expiredDocument->id],
        'contact' => [
            'email' => 'owner@example.com',
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.documents.0', 'Provided documents must exist, belong to your company, and remain active.');

    expect(SupplierApplication::query()->count())->toBe(0);
});

it('allows buyer admins to submit supplier applications', function (): void {
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
        'capabilities' => [
            'methods' => ['cnc_machining'],
        ],
        'address' => '456 Industrial Ave',
        'country' => 'US',
        'city' => 'Austin',
        'moq' => 10,
        'lead_time_days' => 5,
        'contact' => [
            'email' => 'buyer-admin@example.com',
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Supplier application submitted.');

    expect(SupplierApplication::query()->count())->toBe(1)
        ->and($company->fresh()->supplier_status)->toBe(CompanySupplierStatus::Pending);
});

it('blocks other roles from submitting supplier applications', function (): void {
    $owner = User::factory()->create([
        'role' => 'owner',
    ]);

    $company = Company::factory()->create([
        'status' => CompanyStatus::Active,
        'supplier_status' => CompanySupplierStatus::None,
        'owner_user_id' => $owner->id,
    ]);

    $owner->forceFill(['company_id' => $company->id])->save();

    $buyerMember = User::factory()->create([
        'role' => 'buyer_member',
        'company_id' => $company->id,
    ]);

    DB::table('company_user')->insert([
        ['company_id' => $company->id, 'user_id' => $owner->id, 'role' => $owner->role, 'created_at' => now(), 'updated_at' => now()],
        ['company_id' => $company->id, 'user_id' => $buyerMember->id, 'role' => $buyerMember->role, 'created_at' => now(), 'updated_at' => now()],
    ]);

    actingAs($buyerMember);

    $response = $this->postJson('/api/me/apply-supplier', [
        'capabilities' => ['methods' => ['cnc_machining']],
        'address' => '789 Restricted Ave',
    ]);

    $response->assertForbidden();

    expect(SupplierApplication::query()->count())->toBe(0)
        ->and($company->fresh()->supplier_status)->toBe(CompanySupplierStatus::None);
});

it('allows supplier application submission while buyer approval is pending', function (): void {
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

    $response->assertOk()
        ->assertJsonPath('message', 'Supplier application submitted.');

    expect(SupplierApplication::query()->count())->toBe(1)
        ->and($company->fresh()->supplier_status)->toBe(CompanySupplierStatus::Pending);
});

it('requires authentication to list supplier applications', function (): void {
    $response = $this->getJson('/api/supplier-applications');

    $response->assertUnauthorized();
});

it('allows buyer admins to view their supplier applications', function (): void {
    $owner = User::factory()->create([
        'role' => 'owner',
    ]);

    $company = Company::factory()->create([
        'status' => CompanyStatus::Active,
        'supplier_status' => CompanySupplierStatus::Pending,
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

    SupplierApplication::factory()->create([
        'company_id' => $company->id,
        'submitted_by' => $owner->id,
        'status' => SupplierApplicationStatus::Pending,
    ]);

    actingAs($buyerAdmin);

    $response = $this->getJson('/api/supplier-applications');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data.items');
});

it('allows platform admins to view supplier application audit logs', function (): void {
    $company = Company::factory()->create([
        'status' => CompanyStatus::Active,
    ]);

    $application = SupplierApplication::factory()->for($company)->create([
        'status' => SupplierApplicationStatus::Pending,
    ]);

    $admin = User::factory()->create([
        'role' => 'platform_super',
        'company_id' => null,
    ]);

    AuditLog::create([
        'company_id' => $company->id,
        'user_id' => $admin->id,
        'entity_type' => SupplierApplication::class,
        'entity_id' => $application->id,
        'action' => 'created',
        'before' => null,
        'after' => ['status' => 'pending'],
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test-suite',
    ]);

    actingAs($admin);

    $response = getJson("/api/admin/supplier-applications/{$application->id}/audit-logs");

    $response->assertOk()
        ->assertJsonPath('data.items.0.resource.id', (string) $application->id)
        ->assertJsonPath('data.items.0.actor.name', $admin->name)
        ->assertJsonPath('data.items.0.event', 'created');
});

it('rejects non-platform roles requesting supplier application audit logs', function (): void {
    $owner = User::factory()->create([
        'role' => 'owner',
    ]);

    $company = Company::factory()->create([
        'status' => CompanyStatus::Active,
        'owner_user_id' => $owner->id,
    ]);

    $buyerAdmin = User::factory()->for($company)->create([
        'role' => 'buyer_admin',
    ]);

    $application = SupplierApplication::factory()->for($company)->create([
        'status' => SupplierApplicationStatus::Pending,
    ]);

    actingAs($buyerAdmin);

    getJson("/api/admin/supplier-applications/{$application->id}/audit-logs")
        ->assertForbidden();
});

it('allows platform admins with a home company to review tenant applications', function (): void {
    $adminCompany = Company::factory()->create([
        'status' => CompanyStatus::Active,
    ]);

    $admin = User::factory()->create([
        'role' => 'platform_super',
        'company_id' => $adminCompany->id,
    ]);

    $tenantCompany = Company::factory()->create([
        'status' => CompanyStatus::Active,
        'supplier_status' => CompanySupplierStatus::Pending,
    ]);

    SupplierApplication::factory()->for($tenantCompany)->create([
        'status' => SupplierApplicationStatus::Pending,
    ]);

    actingAs($admin);

    $response = $this->getJson('/api/admin/supplier-applications');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data.items');
});

it('allows platform admins with a home company to approve supplier applications', function (): void {
    $adminCompany = Company::factory()->create([
        'status' => CompanyStatus::Active,
    ]);

    $admin = User::factory()->create([
        'role' => 'platform_super',
        'company_id' => $adminCompany->id,
    ]);

    $tenantCompany = Company::factory()->create([
        'status' => CompanyStatus::Active,
        'supplier_status' => CompanySupplierStatus::Pending,
    ]);

    $application = SupplierApplication::factory()->for($tenantCompany)->create([
        'status' => SupplierApplicationStatus::Pending,
    ]);

    actingAs($admin);

    $response = $this->postJson("/api/admin/supplier-applications/{$application->id}/approve");

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', SupplierApplicationStatus::Approved->value);
});
