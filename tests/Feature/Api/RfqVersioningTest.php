<?php

use App\Enums\CompanySupplierStatus;
use App\Enums\CompanyStatus;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Plan;
use App\Models\RFQ;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('increments rfq version and writes audit logs when structural fields change', function (): void {
    $user = makeRfqBuyerAdmin();
    $rfq = makeDraftRfq($user);

    actingAs($user)
        ->putJson("/api/rfqs/{$rfq->getKey()}", [
            'title' => 'Updated Precision Bracket',
            'method' => 'sheet_metal',
        ])
        ->assertOk();

    $rfq->refresh();

    expect($rfq->rfq_version)->toBe(2);

    expect(
        AuditLog::query()
            ->where('entity_type', $rfq->getMorphClass())
            ->where('entity_id', $rfq->getKey())
            ->where('after->meta->reason', 'rfq_updated')
            ->exists()
    )->toBeTrue();
});

it('bumps rfq version when attachments are uploaded', function (): void {
    $user = makeRfqBuyerAdmin();
    $rfq = makeDraftRfq($user);

    config(['documents.disk' => 'local']);
    Storage::fake('local');

    actingAs($user)
        ->post("/api/rfqs/{$rfq->getKey()}/attachments", [
            'file' => UploadedFile::fake()->create('spec.pdf', 10, 'application/pdf'),
            'title' => 'Specs',
        ])
        ->assertCreated();

    $rfq->refresh();

    expect($rfq->rfq_version)->toBe(2);

    expect(
        AuditLog::query()
            ->where('entity_type', $rfq->getMorphClass())
            ->where('entity_id', $rfq->getKey())
            ->where('after->meta->reason', 'rfq_attachment_uploaded')
            ->exists()
    )->toBeTrue();
});

it('increments rfq version when suppliers are invited', function (): void {
    $user = makeRfqBuyerAdmin();
    $rfq = makeDraftRfq($user);

    $supplierCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    $supplier = Supplier::factory()->create([
        'company_id' => $supplierCompany->id,
        'status' => 'approved',
    ]);

    actingAs($user)
        ->postJson("/api/rfqs/{$rfq->getKey()}/invitations", [
            'supplier_ids' => [$supplier->id],
        ])
        ->assertOk();

    $rfq->refresh();

    expect($rfq->rfq_version)->toBe(2);

    expect(
        AuditLog::query()
            ->where('entity_type', $rfq->getMorphClass())
            ->where('entity_id', $rfq->getKey())
            ->where('after->meta->reason', 'rfq_invitation_created')
            ->exists()
    )->toBeTrue();
});

function makeRfqBuyerAdmin(): User
{
    $plan = Plan::factory()->create([
        'code' => 'versioning',
        'price_usd' => 0,
        'rfqs_per_month' => 100,
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    return User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);
}

function makeDraftRfq(User $user): RFQ
{
    return RFQ::factory()->create([
        'company_id' => $user->company_id,
        'created_by' => $user->id,
        'status' => RFQ::STATUS_DRAFT,
        'rfq_version' => 1,
    ]);
}
