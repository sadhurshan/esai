<?php

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RFQ;
use App\Models\RfqItem;
use App\Models\RfqItemAward;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     rfq: RFQ,
 *     buyerUser: User,
 *     rfqItems: array<int, RfqItem>,
 *     suppliers: array<int, array{
 *         supplier: Supplier,
 *         user: User,
 *         quote: Quote,
 *         quote_items: array<int, QuoteItem>
 *     }>
 * }
 */
function buildRfqAwardScenario(int $rfqItemCount = 3, int $supplierCount = 3): array
{
    $plan = Plan::factory()->create([
        'code' => 'enterprise',
        'rfqs_per_month' => 0,
        'invoices_per_month' => 0,
        'users_max' => 0,
        'storage_gb' => 0,
    ]);

    $buyerCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::None->value,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'rfqs_monthly_used' => 0,
        'storage_used_mb' => 0,
    ]);

    $customer = Customer::factory()->create(['company_id' => $buyerCompany->id]);

    Subscription::factory()->create([
        'company_id' => $buyerCompany->id,
        'customer_id' => $customer->id,
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

    $rfq = RFQ::factory()->create([
        'company_id' => $buyerCompany->id,
        'created_by' => $buyerUser->id,
        'status' => 'open',
        'due_at' => now()->addDays(7),
        'due_at' => now()->addDays(7),
        'incoterm' => 'FOB',
        'currency' => 'USD',
        'is_open_bidding' => true,
        'open_bidding' => true,
        'rfq_version' => 1,
    ]);

    $rfqItems = Collection::times($rfqItemCount, function (int $lineNo) use ($rfq): RfqItem {
        return RfqItem::create([
            'rfq_id' => $rfq->id,
            'line_no' => $lineNo,
            'part_name' => 'Part '.$lineNo,
            'spec' => 'Spec '.$lineNo,
            'quantity' => 10 * $lineNo,
            'uom' => 'pcs',
            'target_price' => 50.00 + $lineNo,
        ]);
    })->all();

    $suppliers = [];

    for ($i = 0; $i < $supplierCount; $i++) {
        $supplierPlan = Plan::factory()->create();

        $supplierCompany = Company::factory()->create([
            'status' => CompanyStatus::Active->value,
            'supplier_status' => CompanySupplierStatus::Approved->value,
            'plan_id' => $supplierPlan->id,
            'plan_code' => $supplierPlan->code,
        ]);

        $supplier = Supplier::factory()
            ->for($supplierCompany)
            ->create([
                'status' => 'approved',
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

        $quote = Quote::create([
            'company_id' => $buyerCompany->id,
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'submitted_by' => $supplierUser->id,
            'currency' => 'USD',
            'unit_price' => 100 + ($i * 5),
            'min_order_qty' => null,
            'lead_time_days' => 10 + $i,
            'note' => null,
            'status' => 'submitted',
            'revision_no' => 1,
        ]);

        $quoteItems = [];

        foreach ($rfqItems as $rfqItem) {
            $quoteItems[$rfqItem->id] = QuoteItem::create([
                'quote_id' => $quote->id,
                'company_id' => $buyerCompany->id,
                'rfq_item_id' => $rfqItem->id,
                'unit_price' => 120 + ($i * 3) + $rfqItem->line_no,
                'lead_time_days' => 7 + $i,
                'note' => null,
            ]);
        }

        $suppliers[] = [
            'supplier' => $supplier,
            'user' => $supplierUser,
            'quote' => $quote,
            'quote_items' => $quoteItems,
        ];
    }

    return [
        'rfq' => $rfq,
        'buyerUser' => $buyerUser,
        'rfqItems' => $rfqItems,
        'suppliers' => $suppliers,
    ];
}

it('awards rfq line items across suppliers and drafts purchase orders', function (): void {
    $scenario = buildRfqAwardScenario();

    $rfq = $scenario['rfq'];
    $rfqItems = $scenario['rfqItems'];
    $suppliers = $scenario['suppliers'];
    $buyerUser = $scenario['buyerUser'];

    actingAs($buyerUser);

    $payload = [
        'awards' => [
            [
                'rfq_item_id' => $rfqItems[0]->id,
                'quote_item_id' => $suppliers[0]['quote_items'][$rfqItems[0]->id]->id,
            ],
            [
                'rfq_item_id' => $rfqItems[1]->id,
                'quote_item_id' => $suppliers[0]['quote_items'][$rfqItems[1]->id]->id,
            ],
            [
                'rfq_item_id' => $rfqItems[2]->id,
                'quote_item_id' => $suppliers[1]['quote_items'][$rfqItems[2]->id]->id,
            ],
        ],
    ];

    $response = $this->postJson("/api/rfqs/{$rfq->id}/award-lines", $payload);

    $response
        ->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(2, 'data.items');

    expect(RfqItemAward::count())->toBe(3)
        ->and(PurchaseOrder::count())->toBe(2);

    $rfq->refresh();

    expect($rfq->status)->toBe('awarded')
        ->and($rfq->is_partially_awarded)->toBeFalse();

    foreach ($rfqItems as $rfqItem) {
        $award = RfqItemAward::where('rfq_item_id', $rfqItem->id)->first();
        expect($award)->not->toBeNull();

        $line = PurchaseOrderLine::where('rfq_item_award_id', $award?->id)->first();
        expect($line)->not->toBeNull();
    }

    $winnerQuote = $suppliers[0]['quote']->refresh();
    $secondWinnerQuote = $suppliers[1]['quote']->refresh();
    $loserQuote = $suppliers[2]['quote']->refresh();

    expect($winnerQuote->status)->toBe('awarded')
        ->and($secondWinnerQuote->status)->toBe('awarded')
        ->and($loserQuote->status)->toBe('rejected');

    expect(Notification::where('event_type', 'rfq_line_awarded')->count())->toBe(2)
        ->and(Notification::where('event_type', 'rfq_line_lost')->count())->toBeGreaterThanOrEqual(1);

    $awardAuditCount = AuditLog::where('entity_type', (new RfqItemAward())->getMorphClass())->count();
    expect($awardAuditCount)->toBe(3);
});

it('awards rfq line items without drafting purchase orders via standalone endpoint', function (): void {
    $scenario = buildRfqAwardScenario();

    $rfq = $scenario['rfq'];
    $rfqItems = $scenario['rfqItems'];
    $suppliers = $scenario['suppliers'];
    $buyerUser = $scenario['buyerUser'];

    actingAs($buyerUser);

    $payload = [
        'rfq_id' => $rfq->id,
        'items' => [
            [
                'rfq_item_id' => $rfqItems[0]->id,
                'quote_item_id' => $suppliers[0]['quote_items'][$rfqItems[0]->id]->id,
                'awarded_qty' => 5,
            ],
            [
                'rfq_item_id' => $rfqItems[1]->id,
                'quote_item_id' => $suppliers[1]['quote_items'][$rfqItems[1]->id]->id,
                'awarded_qty' => 3,
            ],
        ],
    ];

    $response = $this->postJson('/api/awards', $payload);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(2, 'data.awards');

    expect(RfqItemAward::count())->toBe(2)
        ->and(PurchaseOrder::count())->toBe(0);

    $awardPayload = $response->json('data.awards');
    expect($awardPayload[0]['po_id'])->toBeNull();
});

it('converts standalone awards into purchase orders grouped by supplier', function (): void {
    $scenario = buildRfqAwardScenario(rfqItemCount: 2, supplierCount: 2);

    $rfq = $scenario['rfq'];
    $rfqItems = $scenario['rfqItems'];
    $suppliers = $scenario['suppliers'];
    $buyerUser = $scenario['buyerUser'];

    actingAs($buyerUser);

    $this->postJson('/api/awards', [
        'rfq_id' => $rfq->id,
        'items' => [
            [
                'rfq_item_id' => $rfqItems[0]->id,
                'quote_item_id' => $suppliers[0]['quote_items'][$rfqItems[0]->id]->id,
            ],
            [
                'rfq_item_id' => $rfqItems[1]->id,
                'quote_item_id' => $suppliers[1]['quote_items'][$rfqItems[1]->id]->id,
            ],
        ],
    ])->assertOk();

    $awardIds = RfqItemAward::pluck('id')->all();

    $response = $this->postJson('/api/pos/from-awards', [
        'award_ids' => $awardIds,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(2, 'data.purchase_orders');

    expect(PurchaseOrder::count())->toBe(2);

    RfqItemAward::all()->each(function (RfqItemAward $award): void {
        expect($award->po_id)->not->toBeNull();

        $line = PurchaseOrderLine::where('rfq_item_award_id', $award->id)->first();
        expect($line)->not->toBeNull();
    });
});

it('rejects conversion when awards span multiple rfqs', function (): void {
    $scenario = buildRfqAwardScenario(rfqItemCount: 1, supplierCount: 2);

    $rfq = $scenario['rfq'];
    $rfqItems = $scenario['rfqItems'];
    $suppliers = $scenario['suppliers'];
    $buyerUser = $scenario['buyerUser'];

    actingAs($buyerUser);

    $this->postJson('/api/awards', [
        'rfq_id' => $rfq->id,
        'items' => [
            [
                'rfq_item_id' => $rfqItems[0]->id,
                'quote_item_id' => $suppliers[0]['quote_items'][$rfqItems[0]->id]->id,
            ],
        ],
    ])->assertOk();

    $otherRfq = RFQ::factory()->create([
        'company_id' => $rfq->company_id,
        'created_by' => $buyerUser->id,
        'status' => 'open',
        'due_at' => now()->addDays(5),
        'due_at' => now()->addDays(5),
        'currency' => 'USD',
    ]);

    $otherItem = RfqItem::create([
        'rfq_id' => $otherRfq->id,
        'line_no' => 99,
        'part_name' => 'Other Part',
        'quantity' => 4,
        'uom' => 'pcs',
    ]);

    $quote = Quote::create([
        'company_id' => $rfq->company_id,
        'rfq_id' => $otherRfq->id,
        'supplier_id' => $suppliers[1]['supplier']->id,
        'submitted_by' => $suppliers[1]['user']->id,
        'currency' => 'USD',
        'unit_price' => 100,
        'lead_time_days' => 7,
        'status' => 'submitted',
        'revision_no' => 1,
    ]);

    $quoteItem = QuoteItem::create([
        'quote_id' => $quote->id,
        'company_id' => $rfq->company_id,
        'rfq_item_id' => $otherItem->id,
        'unit_price' => 120,
        'currency' => 'USD',
        'lead_time_days' => 6,
    ]);

    $this->postJson('/api/awards', [
        'rfq_id' => $otherRfq->id,
        'items' => [
            [
                'rfq_item_id' => $otherItem->id,
                'quote_item_id' => $quoteItem->id,
                'awarded_qty' => 2,
            ],
        ],
    ])->assertOk();

    $awardIds = RfqItemAward::pluck('id')->all();

    $this->postJson('/api/pos/from-awards', [
        'award_ids' => $awardIds,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['award_ids']);
});

it('prevents converting awards that already belong to purchase orders', function (): void {
    $scenario = buildRfqAwardScenario(rfqItemCount: 1, supplierCount: 1);

    $rfq = $scenario['rfq'];
    $rfqItems = $scenario['rfqItems'];
    $suppliers = $scenario['suppliers'];

    actingAs($scenario['buyerUser']);

    $this->postJson("/api/rfqs/{$rfq->id}/award-lines", [
        'awards' => [
            [
                'rfq_item_id' => $rfqItems[0]->id,
                'quote_item_id' => $suppliers[0]['quote_items'][$rfqItems[0]->id]->id,
            ],
        ],
    ])->assertCreated();

    $award = RfqItemAward::firstOrFail();

    $this->postJson('/api/pos/from-awards', [
        'award_ids' => [$award->id],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['award_ids']);
});

it('allows buyers to delete awards and reopen rfq lines', function (): void {
    $scenario = buildRfqAwardScenario(rfqItemCount: 1, supplierCount: 2);

    $rfq = $scenario['rfq'];
    $rfqItems = $scenario['rfqItems'];
    $suppliers = $scenario['suppliers'];
    $buyerUser = $scenario['buyerUser'];

    actingAs($buyerUser);

    $this->postJson('/api/awards', [
        'rfq_id' => $rfq->id,
        'items' => [
            [
                'rfq_item_id' => $rfqItems[0]->id,
                'quote_item_id' => $suppliers[0]['quote_items'][$rfqItems[0]->id]->id,
                'awarded_qty' => 4,
            ],
        ],
    ])->assertOk();

    $award = RfqItemAward::firstOrFail();

    $response = $this->deleteJson("/api/awards/{$award->id}");

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(0, 'data.awards');

    expect(RfqItemAward::count())->toBe(0);

    $rfq->refresh();

    expect($rfq->status)->toBe('open')
        ->and($rfq->is_partially_awarded)->toBeFalse();

    $quoteStatuses = QuoteItem::query()
        ->where('rfq_item_id', $rfqItems[0]->id)
        ->pluck('status')
        ->filter()
        ->unique()
        ->values()
        ->all();

    expect($quoteStatuses)->toBe(['pending']);

    $supplierStatuses = collect($suppliers)
        ->map(fn ($supplier) => $supplier['quote']->refresh()->status)
        ->unique()
        ->values()
        ->all();

    expect($supplierStatuses)->toBe(['submitted']);
});

it('prevents deleting awards once converted to purchase orders', function (): void {
    $scenario = buildRfqAwardScenario();

    $rfq = $scenario['rfq'];
    $rfqItems = $scenario['rfqItems'];
    $suppliers = $scenario['suppliers'];

    actingAs($scenario['buyerUser']);

    $this->postJson("/api/rfqs/{$rfq->id}/award-lines", [
        'awards' => [
            [
                'rfq_item_id' => $rfqItems[0]->id,
                'quote_item_id' => $suppliers[0]['quote_items'][$rfqItems[0]->id]->id,
            ],
        ],
    ])->assertCreated();

    $award = RfqItemAward::firstOrFail();

    $this->deleteJson("/api/awards/{$award->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['po_id']);
});

it('allows re-awarding after cancelling purchase orders', function (): void {
    $scenario = buildRfqAwardScenario(rfqItemCount: 1, supplierCount: 2);

    $rfq = $scenario['rfq'];
    $rfqItem = $scenario['rfqItems'][0];
    $suppliers = $scenario['suppliers'];
    $buyerUser = $scenario['buyerUser'];

    actingAs($buyerUser);

    $this->postJson("/api/rfqs/{$rfq->id}/award-lines", [
        'awards' => [
            [
                'rfq_item_id' => $rfqItem->id,
                'quote_item_id' => $suppliers[0]['quote_items'][$rfqItem->id]->id,
            ],
        ],
    ])->assertCreated();

    $purchaseOrder = PurchaseOrder::firstOrFail();

    expect($purchaseOrder->status)->toBe('draft');

    $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/cancel")
        ->assertOk();

    $purchaseOrder->refresh();

    expect($purchaseOrder->status)->toBe('cancelled');

    expect(
        RfqItemAward::query()
            ->where('po_id', $purchaseOrder->id)
            ->where('status', 'cancelled')
            ->count()
    )->toBe(1);

    $quoteItemStatuses = QuoteItem::query()
        ->where('rfq_item_id', $rfqItem->id)
        ->pluck('status')
        ->filter()
        ->unique()
        ->values()
        ->all();

    expect($quoteItemStatuses)->toBe(['pending']);

    $rfq->refresh();

    expect($rfq->status)->toBe('open')
        ->and($rfq->is_partially_awarded)->toBeFalse();

    $quoteStatuses = Quote::query()
        ->where('rfq_id', $rfq->id)
        ->pluck('status')
        ->unique()
        ->values()
        ->all();

    expect($quoteStatuses)->toBe(['submitted']);

    $this->postJson("/api/rfqs/{$rfq->id}/award-lines", [
        'awards' => [
            [
                'rfq_item_id' => $rfqItem->id,
                'quote_item_id' => $suppliers[1]['quote_items'][$rfqItem->id]->id,
            ],
        ],
    ])->assertCreated();

    $latestPo = PurchaseOrder::query()->orderByDesc('id')->first();

    expect($latestPo)->not->toBeNull()
        ->and($latestPo?->status)->toBe('draft')
        ->and($latestPo?->quote_id)->toBe($suppliers[1]['quote']->id);
});

it('rejects invalid rfq to quote item mappings', function (): void {
    $scenario = buildRfqAwardScenario();

    $rfq = $scenario['rfq'];
    $rfqItems = $scenario['rfqItems'];
    $suppliers = $scenario['suppliers'];

    actingAs($scenario['buyerUser']);

    $payload = [
        'awards' => [
            [
                'rfq_item_id' => $rfqItems[0]->id,
                'quote_item_id' => $suppliers[1]['quote_items'][$rfqItems[1]->id]->id,
            ],
        ],
    ];

    $response = $this->postJson("/api/rfqs/{$rfq->id}/award-lines", $payload);

    $response
        ->assertStatus(422)
        ->assertJsonPath('status', 'error');
});

it('blocks awarding once the rfq deadline has passed', function (): void {
    $scenario = buildRfqAwardScenario();

    $rfq = $scenario['rfq'];
    $rfqItems = $scenario['rfqItems'];
    $supplier = $scenario['suppliers'][0];

    $rfq->update([
        'due_at' => now()->subDay(),
        'due_at' => now()->subDay(),
    ]);

    actingAs($scenario['buyerUser']);

    $response = $this->postJson("/api/rfqs/{$rfq->id}/award-lines", [
        'awards' => [
            [
                'rfq_item_id' => $rfqItems[0]->id,
                'quote_item_id' => $supplier['quote_items'][$rfqItems[0]->id]->id,
            ],
        ],
    ]);

    $response
        ->assertStatus(400)
        ->assertJsonPath('message', 'Deadline passed');
});

it('prevents re-awarding the same rfq line', function (): void {
    $scenario = buildRfqAwardScenario();

    $rfq = $scenario['rfq'];
    $rfqItems = $scenario['rfqItems'];
    $suppliers = $scenario['suppliers'];

    actingAs($scenario['buyerUser']);

    $firstAward = [
        'awards' => [
            [
                'rfq_item_id' => $rfqItems[0]->id,
                'quote_item_id' => $suppliers[0]['quote_items'][$rfqItems[0]->id]->id,
            ],
        ],
    ];

    $this->postJson("/api/rfqs/{$rfq->id}/award-lines", $firstAward)->assertCreated();

    $secondAward = [
        'awards' => [
            [
                'rfq_item_id' => $rfqItems[0]->id,
                'quote_item_id' => $suppliers[1]['quote_items'][$rfqItems[0]->id]->id,
            ],
        ],
    ];

    $response = $this->postJson("/api/rfqs/{$rfq->id}/award-lines", $secondAward);

    $response
        ->assertStatus(409)
        ->assertJsonPath('message', 'RFQ item already awarded.');
});

it('flags rfq as partially awarded when some lines remain open', function (): void {
    $scenario = buildRfqAwardScenario();

    $rfq = $scenario['rfq'];
    $rfqItems = $scenario['rfqItems'];
    $suppliers = $scenario['suppliers'];

    actingAs($scenario['buyerUser']);

    $this->postJson("/api/rfqs/{$rfq->id}/award-lines", [
        'awards' => [
            [
                'rfq_item_id' => $rfqItems[0]->id,
                'quote_item_id' => $suppliers[0]['quote_items'][$rfqItems[0]->id]->id,
            ],
        ],
    ])->assertCreated();

    $rfq->refresh();

    expect($rfq->status)->toBe('open')
        ->and($rfq->is_partially_awarded)->toBeTrue();

    $losingQuote = $suppliers[1]['quote']->refresh();
    expect($losingQuote->status)->toBe('submitted');
});

it('sends regret notifications with lost line item lists', function (): void {
    $scenario = buildRfqAwardScenario();

    $rfq = $scenario['rfq'];
    $rfqItems = $scenario['rfqItems'];
    $suppliers = $scenario['suppliers'];
    $buyerUser = $scenario['buyerUser'];

    actingAs($buyerUser);

    $this->postJson("/api/rfqs/{$rfq->id}/award-lines", [
        'awards' => [
            [
                'rfq_item_id' => $rfqItems[0]->id,
                'quote_item_id' => $suppliers[0]['quote_items'][$rfqItems[0]->id]->id,
            ],
            [
                'rfq_item_id' => $rfqItems[1]->id,
                'quote_item_id' => $suppliers[1]['quote_items'][$rfqItems[1]->id]->id,
            ],
        ],
    ])->assertCreated();

    $loserUser = $suppliers[2]['user'];

    $notification = Notification::query()
        ->where('event_type', 'rfq_line_lost')
        ->where('user_id', $loserUser->id)
        ->first();

    expect($notification)->not->toBeNull()
        ->and($notification?->meta['lost_rfq_item_ids'] ?? [])->toMatchArray([
            $rfqItems[0]->id,
            $rfqItems[1]->id,
        ]);
});
