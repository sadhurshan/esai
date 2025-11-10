<?php

use App\Jobs\SendEsgReminderNotification;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\RfqItem;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\SupplierEsgRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function prepareEsgEnvironment(array $planOverrides = []): array
{
    $defaultPlan = [
        'code' => 'plan-esg-'.Str::uuid()->toString(),
        'risk_scores_enabled' => true,
    ];

    $plan = Plan::factory()->create(array_merge($defaultPlan, $planOverrides));

    $company = Company::factory()->create([
        'plan_code' => $plan->code,
        'status' => 'active',
        'registration_no' => 'REG-2001',
        'tax_id' => 'TAX-2001',
        'country' => 'US',
        'email_domain' => 'example.com',
        'primary_contact_name' => 'Avery Admin',
        'primary_contact_email' => 'avery@example.com',
        'primary_contact_phone' => '+1-555-2222',
    ]);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'stripe_status' => 'active',
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    $supplier = Supplier::factory()->create([
        'company_id' => $company->id,
        'status' => 'approved',
    ]);

    return [$plan, $company, $user, $supplier];
}

it('allows managing supplier ESG certificates and handles lifecycle events', function (): void {
    [$plan, $company, $user, $supplier] = prepareEsgEnvironment();

    Storage::fake('s3');
    config(['documents.disk' => 's3']);

    actingAs($user);

    $now = Carbon::create(2025, 5, 10);
    Carbon::setTestNow($now);

    $upload = UploadedFile::fake()->create('iso14001.pdf', 400, 'application/pdf');

    $createResponse = $this->postJson("/api/suppliers/{$supplier->id}/esg", [
        'category' => 'certificate',
        'name' => 'ISO 14001',
        'description' => 'Environmental management standard',
        'file' => $upload,
        'expires_at' => $now->copy()->addMonths(3)->toDateString(),
    ]);

    $createResponse->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.category', 'certificate');

    $recordId = $createResponse->json('data.id');

    $this->assertDatabaseHas('supplier_esg_records', [
        'id' => $recordId,
        'supplier_id' => $supplier->id,
        'category' => 'certificate',
    ]);

    $listResponse = $this->getJson("/api/suppliers/{$supplier->id}/esg?category=certificate");

    $listResponse->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.download_url', fn (?string $url) => $url !== null);

    $updateResponse = $this->putJson("/api/suppliers/{$supplier->id}/esg/{$recordId}", [
        'description' => 'Updated environmental certification',
        'expires_at' => $now->copy()->addMonths(6)->toDateString(),
    ]);

    $updateResponse->assertOk()
        ->assertJsonPath('data.description', 'Updated environmental certification');

    SupplierEsgRecord::query()->whereKey($recordId)->update([
        'expires_at' => $now->copy()->subDay(),
    ]);

    $expiredAttempt = $this->putJson("/api/suppliers/{$supplier->id}/esg/{$recordId}", [
        'description' => 'Should fail',
    ]);

    $expiredAttempt->assertStatus(422);

    $deleteResponse = $this->deleteJson("/api/suppliers/{$supplier->id}/esg/{$recordId}");
    $deleteResponse->assertOk();

    $this->assertSoftDeleted('supplier_esg_records', ['id' => $recordId]);

    expect(AuditLog::query()
        ->where('entity_type', SupplierEsgRecord::class)
        ->where('entity_id', $recordId)
        ->where('action', 'deleted')
        ->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('generates a scope-3 support pack and stores the export as a document', function (): void {
    [$plan, $company, $user, $supplier] = prepareEsgEnvironment();

    Storage::fake('s3');
    config(['documents.disk' => 's3']);

    actingAs($user);

    $periodStart = Carbon::create(2025, 1, 1);
    $periodEnd = Carbon::create(2025, 12, 31);

    $rfq = RFQ::factory()->create([
        'company_id' => $company->id,
        'status' => 'awarded',
    ]);

    $rfqItem = RfqItem::factory()->create([
        'rfq_id' => $rfq->id,
        'target_price' => 100,
    ]);

    $quote = Quote::create([
        'company_id' => $company->id,
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'currency' => 'USD',
        'unit_price' => 120,
        'min_order_qty' => 10,
        'lead_time_days' => 14,
        'status' => 'awarded',
        'revision_no' => 1,
    ]);

    $purchaseOrder = PurchaseOrder::create([
        'company_id' => $company->id,
        'rfq_id' => $rfq->id,
        'quote_id' => $quote->id,
        'po_number' => 'PO-ESG-1001',
        'currency' => 'USD',
        'status' => 'sent',
    ]);

    PurchaseOrderLine::create([
        'purchase_order_id' => $purchaseOrder->id,
        'rfq_item_id' => $rfqItem->id,
        'line_no' => 1,
        'description' => 'Carbon fiber housing',
        'quantity' => 5,
        'uom' => 'EA',
        'unit_price' => 120,
        'delivery_date' => $periodStart->copy()->addDays(10),
    ]);

    SupplierEsgRecord::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'category' => 'policy',
        'name' => 'Sustainability charter',
    ]);

    SupplierEsgRecord::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'category' => 'emission',
        'name' => 'Scope-3 data',
        'data_json' => ['co2e' => 42.5],
    ]);

    $response = $this->postJson("/api/suppliers/{$supplier->id}/esg/export", [
        'from' => $periodStart->toDateString(),
        'to' => $periodEnd->toDateString(),
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.kind', 'esg_pack')
        ->assertJsonPath('data.documentable_id', $supplier->id);

    $documentId = $response->json('data.id');

    $document = Document::find($documentId);

    expect($document)->not->toBeNull()
        ->and($document->meta['records_included'] ?? null)->toBeGreaterThanOrEqual(2)
        ->and(Storage::disk('s3')->exists($document->path))->toBeTrue();
});

it('blocks ESG endpoints when plan does not include risk and ESG features', function (): void {
    [$plan, $company, $user, $supplier] = prepareEsgEnvironment([
        'risk_scores_enabled' => false,
    ]);

    actingAs($user);

    $response = $this->getJson("/api/suppliers/{$supplier->id}/esg");

    $response->assertStatus(403)
        ->assertJsonPath('status', 'error');
});

it('queues ESG reminder notifications for expired certificates and missing emission data', function (): void {
    [$plan, $company, $user, $supplier] = prepareEsgEnvironment();

    actingAs($user);

    Bus::fake();

    SupplierEsgRecord::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'category' => 'certificate',
        'name' => 'ISO 50001',
        'expires_at' => now()->subDay(),
    ]);

    SupplierEsgRecord::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'category' => 'emission',
        'name' => 'Scope-3 baseline',
        'data_json' => [],
    ]);

    $this->getJson("/api/suppliers/{$supplier->id}/esg")->assertOk();

    Bus::assertDispatched(SendEsgReminderNotification::class, 2);

    Bus::assertDispatched(SendEsgReminderNotification::class, function (SendEsgReminderNotification $job): bool {
        return $job->reason === 'expired_certificate';
    });

    Bus::assertDispatched(SendEsgReminderNotification::class, function (SendEsgReminderNotification $job): bool {
        return $job->reason === 'missing_emission_data';
    });
});
