<?php

use App\Models\AiActionDraft;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ai\Converters\InvoiceDraftConverter;
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

it('converts an approved invoice draft into an invoice record tied to the purchase order', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $supplier = Supplier::factory()->create(['company_id' => $company->id]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'currency' => 'USD',
        'po_number' => 'PO-1001',
        'status' => 'approved',
    ]);

    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'line_no' => 1,
        'description' => 'CNC Widget',
        'quantity' => 10,
        'unit_price' => 30,
        'uom' => 'EA',
    ]);

    $draft = AiActionDraft::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'action_type' => AiActionDraft::TYPE_INVOICE_DRAFT,
        'status' => AiActionDraft::STATUS_APPROVED,
        'input_json' => [
            'query' => 'Create an invoice',
            'inputs' => [
                'po_id' => (string) $purchaseOrder->id,
            ],
            'user_context' => [],
            'filters' => null,
            'entity_context' => [
                'entity_type' => 'purchase_order',
                'entity_id' => $purchaseOrder->id,
            ],
        ],
        'output_json' => [
            'summary' => 'Invoice ready for approval.',
            'payload' => [
                'po_id' => (string) $purchaseOrder->id,
                'invoice_date' => '2025-12-01',
                'due_date' => '2026-01-01',
                'notes' => 'Net 30 payment terms.',
                'line_items' => [
                    [
                        'description' => 'CNC Widget',
                        'qty' => 3,
                        'unit_price' => 25.5,
                        'tax_rate' => 0.08,
                    ],
                ],
            ],
            'citations' => [],
        ],
    ]);

    $converter = app(InvoiceDraftConverter::class);
    $result = $converter->convert($draft, $user);

    expect($result['entity'])->toBeInstanceOf(Invoice::class);

    $invoice = $result['entity']->fresh(['lines']);

    expect($invoice->company_id)->toBe($company->id)
        ->and($invoice->purchase_order_id)->toBe($purchaseOrder->id)
        ->and($invoice->invoice_date?->toDateString())->toBe('2025-12-01')
        ->and($invoice->due_date?->toDateString())->toBe('2026-01-01')
        ->and($invoice->review_note)->toBe('Net 30 payment terms.')
        ->and($invoice->lines)->toHaveCount(1);

    $line = $invoice->lines->first();

    expect($line->po_line_id)->toBe($poLine->id)
        ->and($line->quantity)->toBe(3)
        ->and((string) $line->unit_price)->toBe('25.50');

    expect($draft->fresh()->entity_id)->toBe($invoice->id)
        ->and($draft->fresh()->entity_type)->toBe($invoice->getMorphClass());
});
