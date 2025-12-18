<?php

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RFQ;
use App\Models\RfqItem;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\SupplierRiskScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     rfq: RFQ,
 *     buyerUser: User,
 *     financeUser: User,
 *     quotes: array<int, Quote>
 * }
 */
function buildQuoteComparisonScenario(): array
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

    $financeUser = User::factory()->create([
        'company_id' => $buyerCompany->id,
        'role' => 'finance',
    ]);

    DB::table('company_user')->insert([
        [
            'company_id' => $buyerCompany->id,
            'user_id' => $buyerUser->id,
            'role' => $buyerUser->role,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'company_id' => $buyerCompany->id,
            'user_id' => $financeUser->id,
            'role' => $financeUser->role,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $rfq = RFQ::factory()->create([
        'company_id' => $buyerCompany->id,
        'created_by' => $buyerUser->id,
        'status' => 'open',
        'currency' => 'USD',
        'due_at' => now()->addDays(7),
        'is_open_bidding' => true,
        'open_bidding' => true,
        'rfq_version' => 1,
        'method' => 'cnc',
        'material' => 'Aluminum 6061',
        'finish' => 'Anodizing',
        'tolerance' => '+/- 0.005"',
        'delivery_location' => 'Austin, US',
    ]);

    $rfqItem = RfqItem::factory()->create([
        'rfq_id' => $rfq->id,
        'line_no' => 1,
        'quantity' => 10,
        'uom' => 'pcs',
        'part_name' => 'Precision Bracket',
        'spec' => 'Aluminum 6061',
        'target_price' => 120.00,
    ]);

    $quoteBlueprints = [
        [
            'total_minor' => 100_000,
            'lead_time_days' => 5,
            'risk_score' => 0.9,
            'attachments_count' => 2,
            'incoterm' => 'FOB',
            'payment_terms' => 'Net 30',
            'capabilities' => [
                'methods' => ['cnc milling', 'sheet metal'],
                'materials' => ['aluminum 6061'],
                'finishes' => ['anodizing'],
                'tolerances' => ['+/- 0.005"'],
                'price_band' => 'budget',
            ],
            'document' => [
                'status' => 'valid',
                'expires_at' => now()->addMonths(2),
            ],
        ],
        [
            'total_minor' => 250_000,
            'lead_time_days' => 12,
            'risk_score' => 0.4,
            'attachments_count' => 0,
            'incoterm' => 'CIF',
            'payment_terms' => 'Net 45',
            'capabilities' => [
                'methods' => ['casting'],
                'materials' => ['stainless steel 316'],
                'finishes' => ['powder coat'],
                'tolerances' => ['iso 2768-m'],
                'price_band' => 'premium',
            ],
            'document' => [
                'status' => 'expired',
                'expires_at' => now()->subMonths(1),
            ],
        ],
    ];

    $quotes = [];

    foreach ($quoteBlueprints as $index => $blueprint) {
        $supplierCompany = Company::factory()->create([
            'status' => CompanyStatus::Active->value,
            'supplier_status' => CompanySupplierStatus::Approved->value,
            'plan_id' => $plan->id,
            'plan_code' => $plan->code,
        ]);

        $supplier = Supplier::factory()
            ->for($supplierCompany)
            ->create([
                'status' => 'approved',
                'capabilities' => $blueprint['capabilities'],
            ]);

        SupplierRiskScore::create([
            'company_id' => $supplierCompany->id,
            'supplier_id' => $supplier->id,
            'overall_score' => $blueprint['risk_score'],
        ]);

        SupplierDocument::factory()
            ->for($supplier, 'supplier')
            ->for($supplierCompany, 'company')
            ->create([
                'status' => $blueprint['document']['status'],
                'expires_at' => $blueprint['document']['expires_at'],
                'type' => 'iso9001',
            ]);

        $quote = Quote::create([
            'company_id' => $buyerCompany->id,
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplier->id,
            'currency' => 'USD',
            'unit_price' => 100 + ($index * 25),
            'subtotal' => $blueprint['total_minor'] / 100,
            'subtotal_minor' => $blueprint['total_minor'],
            'tax_amount' => 0,
            'tax_amount_minor' => 0,
            'total_price' => $blueprint['total_minor'] / 100,
            'total_price_minor' => $blueprint['total_minor'],
            'min_order_qty' => null,
            'lead_time_days' => $blueprint['lead_time_days'],
            'notes' => 'Fixture set '.($index + 1),
            'incoterm' => $blueprint['incoterm'],
            'payment_terms' => $blueprint['payment_terms'],
            'status' => 'submitted',
            'revision_no' => 1,
            'attachments_count' => $blueprint['attachments_count'],
            'submitted_at' => now()->subMinutes($index + 1),
        ]);

        QuoteItem::create([
            'quote_id' => $quote->id,
            'company_id' => $buyerCompany->id,
            'rfq_item_id' => $rfqItem->id,
            'unit_price' => ($blueprint['total_minor'] / 100) / 10,
            'unit_price_minor' => (int) round($blueprint['total_minor'] / 10),
            'currency' => 'USD',
            'lead_time_days' => $blueprint['lead_time_days'],
            'status' => 'pending',
        ]);

        $quotes[] = $quote->fresh(['supplier.company', 'items.rfqItem']);
    }

    return [
        'rfq' => $rfq,
        'buyerUser' => $buyerUser,
        'financeUser' => $financeUser,
        'quotes' => $quotes,
    ];
}

it('returns a normalized scoring matrix for buyer sourcing users', function (): void {
    $scenario = buildQuoteComparisonScenario();

    actingAs($scenario['buyerUser']);

    $response = $this->getJson("/api/rfqs/{$scenario['rfq']->id}/quotes/compare");

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(2, 'data.items');

    $items = $response->json('data.items');

    expect($items[0]['scores']['rank'])->toBe(1)
        ->and($items[0]['scores']['price'])->toBe(1)
        ->and($items[1]['scores']['rank'])->toBe(2)
        ->and($items[1]['scores']['price'])->toBe(0.4);

    expect($items[0]['scores']['fit'])->toBeGreaterThan($items[1]['scores']['fit']);
    expect($items[0]['scores']['risk'])->toBe(0.9);

    expect($items[0]['quote']['incoterm'])->toBe('FOB')
        ->and($items[0]['quote']['payment_terms'])->toBe('Net 30');

    $supplierDetails = $items[0]['quote']['supplier'];
    expect($supplierDetails['status'])->toBe('approved')
        ->and($supplierDetails['company']['supplier_status'])->toBe(CompanySupplierStatus::Approved->value)
        ->and($supplierDetails['compliance']['certificates']['valid'])->toBeGreaterThan(0)
        ->and($supplierDetails['compliance']['documents'])->not()->toBeEmpty();

    expect($items[1]['quote']['supplier']['compliance']['certificates']['expired'])->toBeGreaterThan(0);

    expect(collect($items)->pluck('quote.id')->all())
        ->toContain((string) $scenario['quotes'][0]->id)
        ->toContain((string) $scenario['quotes'][1]->id);
});

it('denies comparison access when the user lacks sourcing permission', function (): void {
    $scenario = buildQuoteComparisonScenario();

    actingAs($scenario['financeUser']);

    $response = $this->getJson("/api/rfqs/{$scenario['rfq']->id}/quotes/compare");

    $response
        ->assertForbidden()
        ->assertJsonPath('message', 'Sourcing access required.');
});
