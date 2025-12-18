<?php

use App\Enums\RfqItemAwardStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RFQ;
use App\Models\RfqItem;
use App\Models\RfqItemAward;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

it('returns supplier names for award lines even when suppliers belong to another tenant', function (): void {
    $plan = Plan::factory()->create([
        'code' => 'award-flow',
        'rfqs_per_month' => 25,
        'invoices_per_month' => 25,
        'users_max' => 10,
        'price_usd' => 0,
    ]);

    $buyerCompany = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $supplierCompany = Company::factory()->create();

    $buyer = User::factory()->create([
        'company_id' => $buyerCompany->id,
        'role' => 'buyer_admin',
    ]);

    $supplier = Supplier::factory()->create([
        'company_id' => $supplierCompany->id,
        'name' => 'Alpha Manufacturing',
    ]);

    $rfq = RFQ::factory()->for($buyerCompany)->create([
        'created_by' => $buyer->id,
        'status' => RFQ::STATUS_OPEN,
    ]);

    $rfqItem = RfqItem::factory()->for($rfq)->create([
        'company_id' => $buyerCompany->id,
        'created_by' => $buyer->id,
        'line_no' => 1,
        'quantity' => 10,
        'target_price_minor' => 1500,
        'currency' => 'USD',
    ]);

    $quote = Quote::factory()
        ->for($buyerCompany)
        ->for($rfq)
        ->for($supplier, 'supplier')
        ->create([
            'submitted_by' => $buyer->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

    $quoteItem = QuoteItem::query()->create([
        'company_id' => $buyerCompany->id,
        'quote_id' => $quote->id,
        'rfq_item_id' => $rfqItem->id,
        'unit_price' => 42.50,
        'currency' => 'USD',
        'unit_price_minor' => 4250,
        'lead_time_days' => 14,
        'status' => 'pending',
    ]);

    RfqItemAward::query()->create([
        'company_id' => $buyerCompany->id,
        'rfq_id' => $rfq->id,
        'rfq_item_id' => $rfqItem->id,
        'supplier_id' => $supplier->id,
        'quote_id' => $quote->id,
        'quote_item_id' => $quoteItem->id,
        'awarded_qty' => 5,
        'awarded_by' => $buyer->id,
        'awarded_at' => now(),
        'status' => RfqItemAwardStatus::Awarded,
    ]);

    actingAs($buyer);

    $response = getJson("/api/rfqs/{$rfq->id}/award-candidates");

    $response->assertOk();
    $response->assertJsonPath('data.lines.0.candidates.0.supplier_name', 'Alpha Manufacturing');
    $response->assertJsonPath('data.awards.0.supplier_name', 'Alpha Manufacturing');
});
