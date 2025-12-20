<?php

use App\Enums\RfqItemAwardStatus;
use App\Models\AiWorkflow;
use App\Models\AiWorkflowStep;
use App\Models\Company;
use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RFQ;
use App\Models\RfqItem;
use App\Models\RfqItemAward;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ai\Workflow\PurchaseOrderDraftConverter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Currency::query()->firstOrCreate([
        'code' => 'USD',
    ], [
        'name' => 'US Dollar',
        'minor_unit' => 2,
        'symbol' => '$',
    ]);
});

it('creates or updates purchase orders and lines from an approved PO draft', function (): void {
    Carbon::setTestNow('2025-02-10 08:00:00');

    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $companyId = $company->id;

    $rfq = RFQ::factory()->create([
        'company_id' => $companyId,
        'created_by' => $user->id,
        'status' => RFQ::STATUS_AWARDED,
        'incoterm' => 'FOB',
    ]);

    $rfqItem = RfqItem::factory()->create([
        'company_id' => $companyId,
        'rfq_id' => $rfq->id,
        'created_by' => $user->id,
        'quantity' => 5,
    ]);

    $supplier = Supplier::factory()->create(['company_id' => $companyId]);

    $quote = Quote::factory()->create([
        'company_id' => $companyId,
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'status' => 'awarded',
    ]);

    $quoteItem = QuoteItem::query()->create([
        'company_id' => $companyId,
        'quote_id' => $quote->id,
        'rfq_item_id' => $rfqItem->id,
        'unit_price' => 25,
        'currency' => 'USD',
        'lead_time_days' => 7,
        'status' => 'awarded',
    ]);

    RfqItemAward::query()->create([
        'company_id' => $companyId,
        'rfq_id' => $rfq->id,
        'rfq_item_id' => $rfqItem->id,
        'supplier_id' => $supplier->id,
        'quote_id' => $quote->id,
        'quote_item_id' => $quoteItem->id,
        'awarded_qty' => 5,
        'awarded_by' => $user->id,
        'awarded_at' => now(),
        'status' => RfqItemAwardStatus::Awarded,
    ]);

    $workflow = AiWorkflow::factory()->create([
        'company_id' => $companyId,
        'user_id' => $user->id,
    ]);

    $step = AiWorkflowStep::factory()->create([
        'company_id' => $companyId,
        'workflow_id' => $workflow->workflow_id,
        'action_type' => 'po_draft',
        'input_json' => ['rfq_id' => (string) $rfq->id],
        'output_json' => [
            'summary' => 'PO ready.',
            'payload' => [
                'po_number' => 'PO-AI-1001',
                'rfq_id' => (string) $rfq->id,
                'supplier' => [
                    'supplier_id' => (string) $supplier->id,
                    'name' => $supplier->name,
                ],
                'currency' => 'USD',
                'line_items' => [
                    [
                        'line_number' => 1,
                        'item_code' => (string) $rfqItem->id,
                        'description' => 'CNC Widget',
                        'quantity' => 5,
                        'uom' => 'pcs',
                        'unit_price' => 25,
                        'currency' => 'USD',
                        'subtotal' => 125,
                        'delivery_date' => now()->addDays(14)->toDateString(),
                    ],
                ],
                'delivery_schedule' => [
                    [
                        'milestone' => 'Ship',
                        'date' => now()->addDays(10)->toDateString(),
                        'quantity' => 5,
                        'notes' => 'Single lot',
                    ],
                ],
                'terms_and_conditions' => ['Net 30'],
                'total_value' => 125,
            ],
        ],
        'approval_status' => AiWorkflowStep::APPROVAL_APPROVED,
        'approved_by' => $user->id,
        'approved_at' => now(),
    ]);

    $converter = app(PurchaseOrderDraftConverter::class);
    $result = $converter->convert($step);

    $po = PurchaseOrder::firstWhere('po_number', 'PO-AI-1001');
    $line = $po?->lines()->first();

    expect($result['purchase_order_id'])->toBe($po?->id)
        ->and($result['line_count'])->toBe(1)
        ->and($po)->not()->toBeNull()
        ->and($po?->supplier_id)->toBe($supplier->id)
        ->and($po?->rfq_id)->toBe($rfq->id)
        ->and($po?->subtotal_minor)->toBe(12500)
        ->and($line)->not()->toBeNull()
        ->and($line?->rfq_item_id)->toBe($rfqItem->id)
        ->and($line?->quantity)->toBe(5)
        ->and($line?->unit_price_minor)->toBe(2500);

    $award = RfqItemAward::query()->first();
    expect($award?->po_id)->toBe($po?->id)
        ->and($line?->rfq_item_award_id)->toBe($award?->id);

    // Re-run with updated quantities to ensure idempotent updates rather than duplicate POs.
    $updatedOutput = $step->output_json;
    $updatedOutput['payload']['line_items'][0]['quantity'] = 6;
    $updatedOutput['payload']['line_items'][0]['subtotal'] = 150;
    $step->output_json = $updatedOutput;
    $step->save();

    $converter->convert($step);

    expect(PurchaseOrder::count())->toBe(1)
        ->and(PurchaseOrderLine::count())->toBe(1)
        ->and($po->fresh()->lines()->first()->quantity)->toBe(6)
        ->and($po->fresh()->subtotal_minor)->toBe(15000);
});
