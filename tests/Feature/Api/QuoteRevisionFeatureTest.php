<?php

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\QuoteRevision;
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

beforeEach(function (): void {
    Storage::fake('public');
    config(['documents.disk' => 'public']);
});

/**
 * @return array{
 *     rfq: RFQ,
 *     quote: Quote,
 *     quoteItem: QuoteItem,
 *     supplierUser: User,
 *     buyerUser: User,
 *     supplierCompany: Company,
 *     buyerCompany: Company
 * }
 */
function createQuoteScenario(bool $planAllows = true): array
{
    $growthPlan = Plan::factory()->create([
        'code' => 'growth',
        'quote_revisions_enabled' => $planAllows,
    ]);

    $starterPlan = Plan::factory()->create([
        'code' => 'starter',
        'quote_revisions_enabled' => true,
    ]);

    $buyerCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::None->value,
        'plan_code' => $growthPlan->code,
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
        'company_id' => $buyerCompany->id,
        'role' => 'buyer_admin',
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
        'plan_code' => $starterPlan->code,
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
        'company_id' => $supplierCompany->id,
        'role' => 'supplier_admin',
    ]);

    DB::table('company_user')->insert([
        'company_id' => $supplierCompany->id,
        'user_id' => $supplierUser->id,
        'role' => $supplierUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplier = Supplier::factory()->for($supplierCompany)->create([
        'status' => 'approved',
    ]);

    $rfq = RFQ::factory()->for($buyerCompany)->create([
        'status' => 'open',
        'is_open_bidding' => true,
        'created_by' => $buyerUser->id,
        'due_at' => now()->addDays(3),
    ]);

    $rfqItem = RfqItem::factory()->create([
        'rfq_id' => $rfq->id,
    ]);

    $quote = Quote::create([
        'company_id' => $buyerCompany->id,
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'submitted_by' => $supplierUser->id,
        'currency' => 'USD',
        'unit_price' => 120.00,
        'min_order_qty' => 5,
        'lead_time_days' => 14,
        'note' => 'Initial submission',
        'status' => 'submitted',
        'revision_no' => 1,
    ]);

    $quoteItem = QuoteItem::create([
        'quote_id' => $quote->id,
        'rfq_item_id' => $rfqItem->id,
        'unit_price' => 60.00,
        'lead_time_days' => 14,
        'note' => 'Initial line note',
    ]);

    return compact(
        'rfq',
        'quote',
        'quoteItem',
        'supplierUser',
        'buyerUser',
        'supplierCompany',
        'buyerCompany'
    );
}

it('allows suppliers to submit quote revisions before the deadline', function (): void {
    $scenario = createQuoteScenario();

    $rfq = $scenario['rfq'];
    $quote = $scenario['quote'];
    $quoteItem = $scenario['quoteItem'];
    $supplierUser = $scenario['supplierUser'];
    $buyerUser = $scenario['buyerUser'];

    actingAs($supplierUser);

    $response = $this->postJson("/api/rfqs/{$rfq->id}/quotes/{$quote->id}/revisions", [
        'unit_price' => 180.50,
        'min_order_qty' => 10,
        'items' => [
            [
                'quote_item_id' => $quoteItem->id,
                'unit_price' => 90.25,
                'lead_time_days' => 12,
            ],
        ],
        'attachment' => UploadedFile::fake()->create('revision.pdf', 200, 'application/pdf'),
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.revision_no', 2);

    expect(QuoteRevision::count())->toBe(1);

    $revision = QuoteRevision::first();
    expect($revision)->not->toBeNull()
        ->and($revision?->document_id)->not->toBeNull();

    $quote->refresh();

    expect($quote->revision_no)->toBe(2)
        ->and((float) $quote->unit_price)->toBe(180.50)
        ->and((int) $quote->min_order_qty)->toBe(10);

    $quoteItem->refresh();

    expect((float) $quoteItem->unit_price)->toBe(90.25)
        ->and((int) $quoteItem->lead_time_days)->toBe(12);

    $notification = Notification::query()
        ->where('event_type', 'quote.revision.submitted')
        ->where('user_id', $buyerUser->id)
        ->first();

    expect($notification)->not->toBeNull();
});

it('blocks revisions once the RFQ deadline has passed', function (): void {
    $scenario = createQuoteScenario();

    $rfq = $scenario['rfq'];
    $quote = $scenario['quote'];
    $supplierUser = $scenario['supplierUser'];

    $rfq->update(['due_at' => now()->subDay()]);

    actingAs($supplierUser);

    $response = $this->postJson("/api/rfqs/{$rfq->id}/quotes/{$quote->id}/revisions", [
        'unit_price' => 150.00,
    ]);

    $response
        ->assertStatus(400)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Deadline passed');

    expect(QuoteRevision::count())->toBe(0);
});

it('allows suppliers to withdraw quotes and prevents further revisions', function (): void {
    $scenario = createQuoteScenario();

    $rfq = $scenario['rfq'];
    $quote = $scenario['quote'];
    $quoteItem = $scenario['quoteItem'];
    $supplierUser = $scenario['supplierUser'];
    $buyerUser = $scenario['buyerUser'];

    actingAs($supplierUser);

    $withdrawResponse = $this->postJson("/api/rfqs/{$rfq->id}/quotes/{$quote->id}/withdraw", [
        'reason' => 'Pricing no longer valid',
    ]);

    $withdrawResponse
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', 'withdrawn')
        ->assertJsonPath('data.withdraw_reason', 'Pricing no longer valid');

    $quote->refresh();

    expect($quote->withdrawn_at)->not->toBeNull()
        ->and($quote->withdraw_reason)->toBe('Pricing no longer valid')
        ->and($quote->status)->toBe('withdrawn');

    $notification = Notification::query()
        ->where('event_type', 'quote.withdrawn')
        ->where('user_id', $buyerUser->id)
        ->first();

    expect($notification)->not->toBeNull();

    $revisionResponse = $this->postJson("/api/rfqs/{$rfq->id}/quotes/{$quote->id}/revisions", [
        'items' => [
            [
                'quote_item_id' => $quoteItem->id,
                'note' => 'Attempted update after withdraw',
            ],
        ],
    ]);

    $revisionResponse
        ->assertStatus(400)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Quote has been withdrawn.');

    expect(QuoteRevision::count())->toBe(0);
});

it('allows buyers to review revision history and blocks unrelated suppliers from revising', function (): void {
    $scenario = createQuoteScenario();

    $rfq = $scenario['rfq'];
    $quote = $scenario['quote'];
    $quoteItem = $scenario['quoteItem'];
    $supplierUser = $scenario['supplierUser'];
    $buyerUser = $scenario['buyerUser'];

    actingAs($supplierUser);

    $this->postJson("/api/rfqs/{$rfq->id}/quotes/{$quote->id}/revisions", [
        'unit_price' => 140.00,
        'items' => [
            [
                'quote_item_id' => $quoteItem->id,
                'unit_price' => 70.00,
            ],
        ],
    ])->assertCreated();

    $this->postJson("/api/rfqs/{$rfq->id}/quotes/{$quote->id}/revisions", [
        'note' => 'Additional clarification',
    ])->assertCreated();

    actingAs($buyerUser);

    $historyResponse = $this->getJson("/api/rfqs/{$rfq->id}/quotes/{$quote->id}/revisions");

    $historyResponse
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(2, 'data.items');

    // Unrelated supplier attempt
    $enterprisePlan = Plan::factory()->create([
        'code' => 'enterprise',
        'quote_revisions_enabled' => true,
    ]);

    $otherSupplierCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'plan_code' => $enterprisePlan->code,
    ]);

    $otherCustomer = Customer::factory()->create([
        'company_id' => $otherSupplierCompany->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $otherSupplierCompany->id,
        'customer_id' => $otherCustomer->id,
        'stripe_status' => 'active',
    ]);

    $otherSupplierUser = User::factory()->create([
        'company_id' => $otherSupplierCompany->id,
        'role' => 'supplier_admin',
    ]);

    DB::table('company_user')->insert([
        'company_id' => $otherSupplierCompany->id,
        'user_id' => $otherSupplierUser->id,
        'role' => $otherSupplierUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($otherSupplierUser);

    $blockedResponse = $this->postJson("/api/rfqs/{$rfq->id}/quotes/{$quote->id}/revisions", [
        'unit_price' => 155.00,
    ]);

    $blockedResponse->assertStatus(403);
});

it('returns 402 when the buyer plan disables quote revisions or withdrawals', function (): void {
    $scenario = createQuoteScenario(planAllows: false);

    $rfq = $scenario['rfq'];
    $quote = $scenario['quote'];
    $supplierUser = $scenario['supplierUser'];

    actingAs($supplierUser);

    $revisionResponse = $this->postJson("/api/rfqs/{$rfq->id}/quotes/{$quote->id}/revisions", [
        'unit_price' => 160.00,
    ]);

    $revisionResponse
        ->assertStatus(402)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Upgrade required')
        ->assertJsonPath('errors.code', 'quote_revisions_disabled');

    $withdrawResponse = $this->postJson("/api/rfqs/{$rfq->id}/quotes/{$quote->id}/withdraw", [
        'reason' => 'Unable to fulfill request',
    ]);

    $withdrawResponse
        ->assertStatus(402)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Upgrade required')
        ->assertJsonPath('errors.code', 'quote_revisions_disabled');

    expect(QuoteRevision::count())->toBe(0);
});
