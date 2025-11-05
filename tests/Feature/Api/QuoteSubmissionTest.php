<?php

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\RFQ;
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

it('allows an approved supplier to submit a quote for its company', function (): void {
    Storage::fake('public');

    $plan = Plan::factory()->create([
        'code' => 'starter',
    ]);

    $company = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'is_verified' => true,
        'plan_code' => $plan->code,
        'rfqs_monthly_used' => 0,
        'storage_used_mb' => 0,
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
        'role' => 'supplier_admin',
        'company_id' => $company->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => $user->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplier = Supplier::factory()
        ->for($company)
        ->create([
            'status' => 'approved',
        ]);

    $rfq = RFQ::factory()
        ->for($company)
        ->create([
            'status' => 'open',
            'is_open_bidding' => true,
            'created_by' => $user->id,
        ]);

    $items = RfqItem::factory()->count(2)->create([
        'rfq_id' => $rfq->id,
    ]);

    actingAs($user);

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
        ->assertJsonPath('data.supplier.id', $supplier->id);

    $quote = Quote::with(['items', 'documents'])->first();

    expect($quote)->not->toBeNull()
        ->and($quote->company_id)->toBe($company->id)
        ->and($quote->supplier_id)->toBe($supplier->id)
        ->and($quote->items)->toHaveCount(2)
        ->and($quote->documents)->toHaveCount(1);

    $document = $quote->documents->first();

    expect($document)->not->toBeNull()
        ->and($document->company_id)->toBe($company->id)
        ->and($document->documentable_id)->toBe($quote->id);

    Storage::disk('public')->assertExists($document->path);
});

it('forbids quote submission when the company supplier status is not approved', function (): void {
    Storage::fake('public');

    $plan = Plan::factory()->create([
        'code' => 'starter',
    ]);

    $company = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::Pending->value,
        'is_verified' => false,
        'plan_code' => $plan->code,
        'rfqs_monthly_used' => 0,
        'storage_used_mb' => 0,
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
        'role' => 'supplier_admin',
        'company_id' => $company->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => $user->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplier = Supplier::factory()
        ->for($company)
        ->create([
            'status' => 'approved',
        ]);

    $rfq = RFQ::factory()
        ->for($company)
        ->create([
            'status' => 'open',
            'is_open_bidding' => true,
            'created_by' => $user->id,
        ]);

    $items = RfqItem::factory()->count(1)->create([
        'rfq_id' => $rfq->id,
    ]);

    actingAs($user);

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
        ->and(Document::count())->toBe(0);
});
