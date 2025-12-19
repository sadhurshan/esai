<?php

use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceStatus;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\LineTax;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RFQ;
use App\Models\RfqItem;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Support\Money\Money;
use Illuminate\Support\Str;

it('recalculates quote totals and syncs line taxes', function (): void {
    $user = createMoneyFeatureUser();
    $company = $user->company;

    $taxCode = TaxCode::factory()->for($company)->create([
        'code' => 'VAT10',
        'rate_percent' => 10.0,
        'type' => 'vat',
    ]);

    $rfq = RFQ::factory()->for($company)->create([
        'status' => 'open',
        'currency' => 'USD',
        'created_by' => $user->id,
    ]);

    $rfqItem = RfqItem::factory()->create([
        'rfq_id' => $rfq->id,
        'quantity' => 5,
        'uom' => 'EA',
        'target_price_minor' => 12000,
        'currency' => 'USD',
    ]);

    $supplier = Supplier::factory()->create([
        'company_id' => $company->id,
        'status' => 'approved',
    ]);

    $quote = Quote::create([
        'company_id' => $company->id,
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'currency' => 'USD',
        'unit_price' => '0.00',
        'subtotal' => '0.00',
        'tax_amount' => '0.00',
        'total' => '0.00',
        'subtotal_minor' => 0,
        'tax_amount_minor' => 0,
        'total_minor' => 0,
        'status' => 'submitted',
        'lead_time_days' => 10,
        'revision_no' => 1,
    ]);

    $quoteItem = QuoteItem::create([
        'quote_id' => $quote->id,
        'rfq_item_id' => $rfqItem->id,
        'unit_price' => 120,
        'unit_price_minor' => null,
        'currency' => 'USD',
        'lead_time_days' => 10,
        'status' => 'pending',
    ]);

    LineTax::create([
        'company_id' => $company->id,
        'tax_code_id' => $taxCode->id,
        'taxable_type' => QuoteItem::class,
        'taxable_id' => $quoteItem->id,
        'rate_percent' => 10.0,
        'amount_minor' => 0,
        'sequence' => 1,
    ]);

    $this->actingAs($user);

    $response = $this->postJson("/api/quotes/{$quote->id}/recalculate");

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Quote totals recalculated.')
        ->assertJsonPath('data.total_minor', 66000)
        ->assertJsonPath('data.tax_amount_minor', 6000);

    $quote->refresh();
    $quoteItem->refresh();

    expect((int) $quote->subtotal_minor)->toBe(60000)
        ->and((int) $quote->tax_amount_minor)->toBe(6000)
        ->and((int) $quote->total_minor)->toBe(66000)
        ->and((int) $quoteItem->unit_price_minor)->toBe(12000);

    $taxAmounts = $quoteItem->taxes()->pluck('amount_minor')->all();
    expect($taxAmounts)->toEqual([6000]);
});

it('recalculates purchase order totals with taxes', function (): void {
    $user = createMoneyFeatureUser();
    $company = $user->company;

    $taxCode = TaxCode::factory()->for($company)->create([
        'code' => 'VAT10',
        'rate_percent' => 10.0,
        'type' => 'vat',
    ]);

    $purchaseOrder = PurchaseOrder::factory()->for($company)->create([
        'currency' => 'USD',
        'status' => 'draft',
        'subtotal' => '0.00',
        'tax_amount' => '0.00',
        'total' => '0.00',
        'subtotal_minor' => 0,
        'tax_amount_minor' => 0,
        'total_minor' => 0,
    ]);

    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'line_no' => 1,
        'description' => 'Custom bracket',
        'quantity' => 4,
        'uom' => 'EA',
        'unit_price' => 250,
        'unit_price_minor' => null,
        'currency' => 'USD',
    ]);

    LineTax::create([
        'company_id' => $company->id,
        'tax_code_id' => $taxCode->id,
        'taxable_type' => PurchaseOrderLine::class,
        'taxable_id' => $poLine->id,
        'rate_percent' => 10.0,
        'amount_minor' => 0,
        'sequence' => 1,
    ]);

    $this->actingAs($user);

    $response = $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/recalculate");

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Purchase order totals recalculated.')
        ->assertJsonPath('data.total_minor', 110000)
        ->assertJsonPath('data.tax_amount_minor', 10000);

    $purchaseOrder->refresh();
    $poLine->refresh();

    expect((int) $purchaseOrder->subtotal_minor)->toBe(100000)
        ->and((int) $purchaseOrder->tax_amount_minor)->toBe(10000)
        ->and((int) $purchaseOrder->total_minor)->toBe(110000)
        ->and((int) $poLine->unit_price_minor)->toBe(25000);

    $taxAmounts = $poLine->taxes()->pluck('amount_minor')->all();
    expect($taxAmounts)->toEqual([10000]);
});

it('recalculates invoice totals and updates line taxes', function (): void {
    $user = createMoneyFeatureUser();
    $company = $user->company;

    $taxCode = TaxCode::factory()->for($company)->create([
        'code' => 'VAT10',
        'rate_percent' => 10.0,
        'type' => 'vat',
    ]);

    $purchaseOrder = PurchaseOrder::factory()->for($company)->create([
        'currency' => 'USD',
        'status' => 'sent',
    ]);

    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'line_no' => 1,
        'description' => 'Custom plate',
        'quantity' => 2,
        'uom' => 'EA',
        'unit_price' => 75,
        'unit_price_minor' => Money::fromDecimal(75, 'USD', 2)->amountMinor(),
        'currency' => 'USD',
    ]);

    $supplier = Supplier::factory()->create([
        'company_id' => $company->id,
        'status' => 'approved',
    ]);

    $invoice = Invoice::create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'supplier_id' => $supplier->id,
        'invoice_number' => 'INV-'.Str::upper(Str::random(6)),
        'currency' => 'USD',
        'subtotal' => '0.00',
        'tax_amount' => '0.00',
        'total' => '0.00',
        'status' => InvoiceStatus::Draft->value,
    ]);

    $invoiceLine = InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'po_line_id' => $poLine->id,
        'description' => 'Custom plate',
        'quantity' => 2,
        'uom' => 'EA',
        'currency' => 'USD',
        'unit_price' => 75,
        'unit_price_minor' => null,
    ]);

    LineTax::create([
        'company_id' => $company->id,
        'tax_code_id' => $taxCode->id,
        'taxable_type' => InvoiceLine::class,
        'taxable_id' => $invoiceLine->id,
        'rate_percent' => 10.0,
        'amount_minor' => 0,
        'sequence' => 1,
    ]);

    $this->actingAs($user);

    $response = $this->postJson("/api/invoices/{$invoice->id}/recalculate");

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Invoice totals recalculated.')
        ->assertJsonPath('data.total', 165)
        ->assertJsonPath('data.tax_amount', 15);

    $invoice->refresh();
    $invoiceLine->refresh();

    expect((float) $invoice->subtotal)->toBe(150.0)
        ->and((float) $invoice->tax_amount)->toBe(15.0)
        ->and((float) $invoice->total)->toBe(165.0)
        ->and((int) $invoiceLine->unit_price_minor)->toBe(7500);

    $taxAmounts = $invoiceLine->taxes()->pluck('amount_minor')->all();
    expect($taxAmounts)->toEqual([1500]);
});

it('recalculates credit note amount from decimal value', function (): void {
    $user = createMoneyFeatureUser();
    $company = $user->company;

    $purchaseOrder = PurchaseOrder::factory()->for($company)->create([
        'currency' => 'USD',
    ]);

    $supplier = Supplier::factory()->create([
        'company_id' => $company->id,
        'status' => 'approved',
    ]);

    $invoice = Invoice::create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'supplier_id' => $supplier->id,
        'invoice_number' => 'INV-'.Str::upper(Str::random(6)),
        'currency' => 'USD',
        'subtotal' => '0.00',
        'tax_amount' => '0.00',
        'total' => '0.00',
        'status' => InvoiceStatus::Draft->value,
    ]);

    $creditNote = CreditNote::create([
        'company_id' => $company->id,
        'invoice_id' => $invoice->id,
        'purchase_order_id' => $purchaseOrder->id,
        'credit_number' => 'CN-'.Str::upper(Str::random(6)),
        'currency' => 'USD',
        'amount' => '120.50',
        'amount_minor' => null,
        'reason' => 'Pricing adjustment',
        'status' => CreditNoteStatus::Draft,
    ]);

    $this->actingAs($user);

    $response = $this->postJson("/api/credit-notes/{$creditNote->id}/recalculate");

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Credit note total recalculated.')
        ->assertJsonPath('data.amount_minor', 12050);

    $creditNote->refresh();

    expect($creditNote->amount)->toBe('120.50')
        ->and((int) $creditNote->amount_minor)->toBe(12050);
});

it('rejects purchase order recalculation when line currency mismatches', function (): void {
    $user = createMoneyFeatureUser();
    $company = $user->company;

    $purchaseOrder = PurchaseOrder::factory()->for($company)->create([
        'currency' => 'USD',
    ]);

    PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'line_no' => 1,
        'description' => 'Metric fasteners',
        'quantity' => 5,
        'uom' => 'EA',
        'unit_price' => 15,
        'currency' => 'EUR',
    ]);

    $this->actingAs($user);

    $response = $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/recalculate");

    $response->assertStatus(422)
        ->assertJsonPath('message', 'All purchase order lines must use the same currency as the purchase order.')
        ->assertJsonPath('errors.lines.0', 'All purchase order lines must use the same currency as the purchase order.');
});
