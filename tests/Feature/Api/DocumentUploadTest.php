<?php

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Plan;
use App\Models\RFQ;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['documents.disk' => 'public']);
});

it('stores a document for an rfq and returns resource data', function (): void {
    Storage::fake('public');

    $plan = Plan::factory()->create([
        'code' => 'starter',
    ]);

    $company = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'plan_code' => $plan->code,
        'supplier_status' => 'approved',
        'is_verified' => true,
        'rfqs_monthly_used' => 0,
        'invoices_monthly_used' => 0,
        'storage_used_mb' => 0,
        'registration_no' => 'REG-2001',
        'tax_id' => 'TAX-2001',
        'country' => 'US',
        'email_domain' => 'example.org',
        'primary_contact_name' => 'Example Owner',
        'primary_contact_email' => 'owner@example.org',
        'primary_contact_phone' => '+1-555-0100',
    ]);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'stripe_status' => 'active',
    ]);

    $user = User::factory()->for($company)->create([
        'role' => 'buyer_admin',
    ]);

    actingAs($user);

    $rfq = RFQ::factory()->for($company)->create([
        'status' => 'draft',
        'created_by' => $user->id,
    ]);

    $file = UploadedFile::fake()->create('drawing.pdf', 256, 'application/pdf');

    $response = $this->postJson('/api/documents', [
        'entity' => 'rfq',
        'entity_id' => $rfq->id,
        'kind' => 'rfq',
        'category' => 'technical',
        'visibility' => 'company',
        'file' => $file,
        'meta' => ['label' => 'Initial CAD'],
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.kind', 'rfq')
        ->assertJsonPath('data.category', 'technical')
        ->assertJsonPath('data.documentable_id', $rfq->id);

    $stored = Document::first();

    expect($stored)->not->toBeNull()
        ->and($stored->company_id)->toBe($company->id)
        ->and($stored->documentable_id)->toBe($rfq->id)
        ->and($stored->kind)->toBe('rfq')
        ->and($stored->category)->toBe('technical')
        ->and($stored->meta['label'])->toBe('Initial CAD');

    Storage::disk('public')->assertExists($stored->path);
});

it('returns not found when attempting to attach documents to another company entity', function (): void {
    Storage::fake('public');

    $plan = Plan::factory()->create([
        'code' => 'starter',
    ]);

    $ownerCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'plan_code' => $plan->code,
        'supplier_status' => 'approved',
        'is_verified' => true,
        'rfqs_monthly_used' => 0,
        'invoices_monthly_used' => 0,
        'storage_used_mb' => 0,
        'registration_no' => 'REG-3001',
        'tax_id' => 'TAX-3001',
        'country' => 'US',
        'email_domain' => 'owner.example',
        'primary_contact_name' => 'Owner Contact',
        'primary_contact_email' => 'owner@owner.example',
        'primary_contact_phone' => '+1-555-0200',
    ]);

    $customer = Customer::factory()->create([
        'company_id' => $ownerCompany->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $ownerCompany->id,
        'customer_id' => $customer->id,
        'stripe_status' => 'active',
    ]);

    $user = User::factory()->for($ownerCompany)->create([
        'role' => 'buyer_admin',
    ]);

    actingAs($user);

    $otherCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'plan_code' => $plan->code,
        'supplier_status' => 'approved',
        'is_verified' => true,
        'rfqs_monthly_used' => 0,
        'invoices_monthly_used' => 0,
        'storage_used_mb' => 0,
    ]);

    $foreignRfq = RFQ::factory()->for($otherCompany)->create([
        'status' => 'draft',
    ]);

    $file = UploadedFile::fake()->create('spec.pdf', 128, 'application/pdf');

    $response = $this->postJson('/api/documents', [
        'entity' => 'rfq',
        'entity_id' => $foreignRfq->id,
        'kind' => 'rfq',
        'category' => 'technical',
        'visibility' => 'company',
        'file' => $file,
    ]);

    $response->assertNotFound();
    expect(Document::count())->toBe(0);
});
