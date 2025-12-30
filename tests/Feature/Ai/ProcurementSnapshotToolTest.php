<?php

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\GoodsReceiptNote;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\Supplier;
use App\Services\Ai\WorkspaceToolResolver;
use Illuminate\Support\Carbon;

it('aggregates procurement lifecycle data via workspace.procurement_snapshot', function (): void {
    Carbon::setTestNow(Carbon::parse('2025-12-25T08:00:00Z'));

    $company = Company::factory()->create();
    $supplier = Supplier::factory()->create(['company_id' => $company->id]);

    $rfqPrimary = RFQ::factory()->create([
        'company_id' => $company->id,
        'number' => 'RFQ-SNAPSHOT-1',
        'title' => 'Legacy Machining RFQ',
        'status' => RFQ::STATUS_OPEN,
        'due_at' => now()->addDays(5),
    ]);

    RFQ::factory()->create([
        'company_id' => $company->id,
        'number' => 'RFQ-SNAPSHOT-2',
        'title' => 'New Casting RFQ',
        'status' => RFQ::STATUS_AWARDED,
        'due_at' => now()->addDays(10),
    ]);

    Quote::factory()->create([
        'company_id' => $company->id,
        'rfq_id' => $rfqPrimary->id,
        'supplier_id' => $supplier->id,
        'status' => 'submitted',
        'submitted_at' => now()->subDay(),
        'currency' => 'USD',
        'total_price' => 1500,
        'total_price_minor' => 150000,
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'po_number' => 'PO-10001',
        'status' => 'sent',
        'currency' => 'USD',
        'total' => 1250,
        'total_minor' => 125000,
        'ordered_at' => now()->subDays(2),
        'expected_at' => now()->addDays(7),
    ]);

    GoodsReceiptNote::factory()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'number' => 'GRN-5001',
        'status' => 'complete',
        'inspected_at' => now()->subDay(),
    ]);

    Invoice::factory()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'supplier_id' => $supplier->id,
        'supplier_company_id' => $supplier->company_id,
        'invoice_number' => 'INV-9001',
        'status' => InvoiceStatus::Approved->value,
        'currency' => 'USD',
        'subtotal' => 1000,
        'subtotal_minor' => 100000,
        'tax_amount' => 250,
        'tax_minor' => 25000,
        'total' => 1250,
        'total_minor' => 125000,
        'due_date' => now()->addDays(30),
    ]);

    $noiseCompany = Company::factory()->create();
    RFQ::factory()->count(2)->create(['company_id' => $noiseCompany->id]);

    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.procurement_snapshot',
        'call_id' => 'snapshot-test',
        'arguments' => ['limit' => 1],
    ]]);

    $payload = $results[0]['result'];

    expect($payload)->toHaveKeys(['rfqs', 'quotes', 'purchase_orders', 'receipts', 'invoices', 'meta']);
    expect($payload['meta']['limit'])->toBe(1)
        ->and($payload['meta']['generated_at'])->toBeString();

    expect($payload['rfqs']['total_count'])->toBe(2)
        ->and($payload['rfqs']['status_counts'])->toHaveKey(RFQ::STATUS_OPEN)
        ->and($payload['rfqs']['latest'])->toHaveCount(1)
        ->and($payload['rfqs']['latest'][0]['number'])->toBe('RFQ-SNAPSHOT-2');

    expect($payload['quotes']['status_counts'])->toHaveKey('submitted')
        ->and($payload['quotes']['latest'][0]['supplier']['name'])->toBe($supplier->name);

    expect($payload['purchase_orders']['latest'][0]['po_number'])->toBe('PO-10001')
        ->and($payload['receipts']['latest'][0]['receipt_number'])->toBe('GRN-5001')
        ->and($payload['invoices']['latest'][0]['invoice_number'])->toBe('INV-9001');

    Carbon::setTestNow();
});
