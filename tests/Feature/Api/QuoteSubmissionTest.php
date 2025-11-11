<?php

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\RfqInvitation;
use App\Models\RfqItem;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['documents.disk' => 'public']);
});

it('allows an approved supplier to submit a quote to another company\'s rfq', function (): void {
    Storage::fake('public');

    $plan = Plan::factory()->create([
        'code' => 'starter',
    ]);

    $buyerCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::None->value,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'rfqs_monthly_used' => 0,
        'storage_used_mb' => 0,
    ]);

    $buyerCustomer = Customer::factory()->create([
        'company_id' => $buyerCompany->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $buyerCompany->id,
        'customer_id' => $buyerCustomer->id,
        'stripe_status' => 'active',
    ]);

    $buyerUser = User::factory()->create([
        'role' => 'buyer_admin',
        'company_id' => $buyerCompany->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $buyerCompany->id,
        'user_id' => $buyerUser->id,
        'role' => $buyerUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplierCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'is_verified' => true,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'rfqs_monthly_used' => 0,
        'storage_used_mb' => 0,
    ]);

    $supplierCustomer = Customer::factory()->create([
        'company_id' => $supplierCompany->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $supplierCompany->id,
        'customer_id' => $supplierCustomer->id,
        'stripe_status' => 'active',
    ]);

    $supplierUser = User::factory()->create([
        'role' => 'supplier_admin',
        'company_id' => $supplierCompany->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $supplierCompany->id,
        'user_id' => $supplierUser->id,
        'role' => $supplierUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplier = Supplier::factory()
        ->for($supplierCompany)
        ->create([
            'status' => 'approved',
        ]);

    $rfq = RFQ::factory()
        ->for($buyerCompany)
        ->create([
            'status' => 'open',
            'is_open_bidding' => true,
            'created_by' => $buyerUser->id,
        ]);

    $items = RfqItem::factory()->count(2)->create([
        'rfq_id' => $rfq->id,
    ]);

    actingAs($supplierUser);

    $payload = [
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'currency' => 'USD',
        'unit_price' => 145.50,
        'lead_time_days' => 18,
        'min_order_qty' => 5,
        'note' => 'Includes expedited shipping.',
        'items' => $items->map(fn (RfqItem $item) => [
            'rfq_item_id' => $item->id,
            'unit_price' => 72.75,
            'lead_time_days' => 18,
        ])->toArray(),
        'attachment' => UploadedFile::fake()->create('quote.pdf', 200),
    ];

    $response = $this->postJson('/api/quotes', $payload);

    $response
        ->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.supplier.id', $supplier->id)
        ->assertJsonPath('data.rfq_id', $rfq->id);

    $quote = Quote::with(['items', 'documents'])->first();

    expect($quote)->not->toBeNull()
        ->and($quote->company_id)->toBe($rfq->company_id)
        ->and($quote->supplier_id)->toBe($supplier->id)
        ->and($quote->items)->toHaveCount(2)
        ->and($quote->documents)->toHaveCount(1);

    $document = $quote->documents->first();

    expect($document)->not->toBeNull()
    ->and($document->company_id)->toBe($rfq->company_id)
        ->and($document->documentable_id)->toBe($quote->id);

    Storage::disk('public')->assertExists($document->path);
});

it('forbids quote submission when the supplier company is not approved even if invited', function (): void {
    Storage::fake('public');

    $plan = Plan::factory()->create([
        'code' => 'starter',
    ]);

    $buyerCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::None->value,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'rfqs_monthly_used' => 0,
        'storage_used_mb' => 0,
    ]);

    $buyerCustomer = Customer::factory()->create([
        'company_id' => $buyerCompany->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $buyerCompany->id,
        'customer_id' => $buyerCustomer->id,
        'stripe_status' => 'active',
    ]);

    $buyerUser = User::factory()->create([
        'role' => 'buyer_admin',
        'company_id' => $buyerCompany->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $buyerCompany->id,
        'user_id' => $buyerUser->id,
        'role' => $buyerUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplierCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::Pending->value,
        'is_verified' => false,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'rfqs_monthly_used' => 0,
        'storage_used_mb' => 0,
    ]);

    $supplierCustomer = Customer::factory()->create([
        'company_id' => $supplierCompany->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $supplierCompany->id,
        'customer_id' => $supplierCustomer->id,
        'stripe_status' => 'active',
    ]);

    $supplierUser = User::factory()->create([
        'role' => 'supplier_admin',
        'company_id' => $supplierCompany->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $supplierCompany->id,
        'user_id' => $supplierUser->id,
        'role' => $supplierUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplier = Supplier::factory()
        ->for($supplierCompany)
        ->create([
            'status' => 'approved',
        ]);

    $rfq = RFQ::factory()
        ->for($buyerCompany)
        ->create([
            'status' => 'open',
            'is_open_bidding' => false,
            'created_by' => $buyerUser->id,
        ]);

    $invitation = RfqInvitation::create([
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'invited_by' => $buyerUser->id,
        'status' => 'invited',
    ]);

    $items = RfqItem::factory()->count(1)->create([
        'rfq_id' => $rfq->id,
    ]);

    actingAs($supplierUser);

    $payload = [
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'currency' => 'USD',
        'unit_price' => 120.00,
        'lead_time_days' => 14,
        'min_order_qty' => 5,
        'items' => $items->map(fn (RfqItem $item) => [
            'rfq_item_id' => $item->id,
            'unit_price' => 60.00,
            'lead_time_days' => 14,
        ])->toArray(),
    ];

    $response = $this->postJson('/api/quotes', $payload);

    $response->assertForbidden();

    expect(Quote::count())->toBe(0)
        ->and(Document::count())->toBe(0)
        ->and(RfqInvitation::count())->toBe(1);
});

it('requires an invitation when rfq is not open bidding', function (): void {
    Storage::fake('public');

    $plan = Plan::factory()->create([
        'code' => 'starter',
    ]);

    $buyerCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::None->value,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $buyerCustomer = Customer::factory()->create([
        'company_id' => $buyerCompany->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $buyerCompany->id,
        'customer_id' => $buyerCustomer->id,
        'stripe_status' => 'active',
    ]);

    $buyerUser = User::factory()->create([
        'role' => 'buyer_admin',
        'company_id' => $buyerCompany->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $buyerCompany->id,
        'user_id' => $buyerUser->id,
        'role' => $buyerUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplierCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'is_verified' => true,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $supplierCustomer = Customer::factory()->create([
        'company_id' => $supplierCompany->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $supplierCompany->id,
        'customer_id' => $supplierCustomer->id,
        'stripe_status' => 'active',
    ]);

    $supplierUser = User::factory()->create([
        'role' => 'supplier_admin',
        'company_id' => $supplierCompany->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $supplierCompany->id,
        'user_id' => $supplierUser->id,
        'role' => $supplierUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplier = Supplier::factory()
        ->for($supplierCompany)
        ->create([
            'status' => 'approved',
        ]);

    $rfq = RFQ::factory()
        ->for($buyerCompany)
        ->create([
            'status' => 'open',
            'is_open_bidding' => false,
            'created_by' => $buyerUser->id,
        ]);

    $items = RfqItem::factory()->count(1)->create([
        'rfq_id' => $rfq->id,
    ]);

    actingAs($supplierUser);

    $payload = [
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'currency' => 'USD',
        'unit_price' => 210.00,
        'lead_time_days' => 20,
        'min_order_qty' => 10,
        'items' => $items->map(fn (RfqItem $item) => [
            'rfq_item_id' => $item->id,
            'unit_price' => 105.00,
            'lead_time_days' => 20,
        ])->toArray(),
    ];

    $response = $this->postJson('/api/quotes', $payload);

    $response->assertForbidden();

    expect(Quote::count())->toBe(0)
        ->and(Document::count())->toBe(0)
        ->and(RfqInvitation::count())->toBe(0);
});
