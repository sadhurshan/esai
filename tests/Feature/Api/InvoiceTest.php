<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\GoodsReceiptLine;
use App\Models\GoodsReceiptNote;
use App\Models\InvoiceLine;
use App\Models\InvoiceMatch;
use App\Models\Plan;
use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use App\Models\TaxCode;
use App\Notifications\InvoiceMatchResultNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

function provisionInvoiceContext(int $invoiceCap = 5): array
{
    Currency::query()->firstOrCreate(
        ['code' => 'USD'],
        [
            'name' => 'US Dollar',
            'minor_unit' => 2,
            'symbol' => '$',
        ]
    );

    $plan = Plan::factory()->create([
        'code' => 'invoice-'.Str::lower(Str::random(6)),
        'rfqs_per_month' => 25,
        'invoices_per_month' => $invoiceCap,
        'users_max' => 10,
        'storage_gb' => 5,
    ]);

    $company = Company::factory()->create([
        'plan_code' => $plan->code,
        'rfqs_monthly_used' => 0,
        'invoices_monthly_used' => 0,
    ]);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'stripe_status' => 'active',
    ]);

    $financeUser = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'finance',
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'sent',
        'tax_percent' => 10,
        'currency' => 'USD',
    ]);

    $poLines = [
        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'description' => 'Precision plates',
            'quantity' => 5,
            'unit_price' => 120,
            'uom' => 'EA',
        ]),
        PurchaseOrderLine::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'description' => 'Fastener packs',
            'quantity' => 2,
            'unit_price' => 80,
            'uom' => 'EA',
        ]),
    ];

    $supplier = Supplier::factory()->create([
        'company_id' => $company->id,
        'status' => 'approved',
    ]);

    $taxCode = TaxCode::factory()->for($company)->create([
        'code' => 'VAT10',
        'rate_percent' => 10.0,
        'type' => 'vat',
    ]);

    return [
        'plan' => $plan,
        'company' => $company,
        'finance' => $financeUser,
        'purchaseOrder' => $purchaseOrder,
        'poLines' => $poLines,
        'supplier' => $supplier,
        'taxCode' => $taxCode,
    ];
}

test('finance user can create invoice and receives match summary', function (): void {
    [
        'company' => $company,
        'finance' => $financeUser,
        'purchaseOrder' => $purchaseOrder,
        'poLines' => $poLines,
        'supplier' => $supplier,
        'taxCode' => $taxCode,
    ] = provisionInvoiceContext();

    Notification::fake();

    $this->actingAs($financeUser);

    $payload = [
        'supplier_id' => $supplier->id,
        'lines' => [
            [
                'po_line_id' => $poLines[0]->id,
                'description' => 'Precision plates',
                'quantity' => 3,
                'uom' => 'EA',
                'unit_price' => 120,
                'tax_code_ids' => [$taxCode->id],
            ],
            [
                'po_line_id' => $poLines[1]->id,
                'description' => 'Fastener packs',
                'quantity' => 2,
                'uom' => 'EA',
                'unit_price' => 80,
                'tax_code_ids' => [$taxCode->id],
            ],
        ],
    ];

    $response = $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/invoices", $payload);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Invoice created.')
        ->assertJsonPath('data.purchase_order_id', $purchaseOrder->id)
        ->assertJsonPath('data.match_summary.qty_mismatch', 2);

    $invoiceId = (int) $response->json('data.id');

    $company->refresh();
    expect($company->invoices_monthly_used)->toBe(1);

    $this->assertDatabaseHas('invoices', [
        'id' => $invoiceId,
        'company_id' => $company->id,
        'subtotal' => '520.00',
        'tax_amount' => '52.00',
        'total' => '572.00',
    ]);

    $this->assertDatabaseHas('line_taxes', [
        'taxable_type' => InvoiceLine::class,
        'company_id' => $company->id,
        'tax_code_id' => $taxCode->id,
        'amount_minor' => 3600,
    ]);

    $results = InvoiceMatch::query()
        ->where('invoice_id', $invoiceId)
        ->pluck('result')
        ->all();

    expect($results)->toHaveCount(2)
        ->and(array_unique($results))->toEqual(['qty_mismatch']);
});

test('finance user can create invoice via from-po endpoint', function (): void {
    [
        'company' => $company,
        'finance' => $financeUser,
        'purchaseOrder' => $purchaseOrder,
        'poLines' => $poLines,
        'supplier' => $supplier,
        'taxCode' => $taxCode,
    ] = provisionInvoiceContext();

    Notification::fake();

    $this->actingAs($financeUser);

    $payload = [
        'po_id' => $purchaseOrder->id,
        'supplier_id' => $supplier->id,
        'invoice_number' => 'INV-PO-FROM-ENDPOINT',
        'lines' => [
            [
                'po_line_id' => $poLines[0]->id,
                'description' => 'Precision plates',
                'qty_invoiced' => 1,
                'uom' => 'EA',
                'unit_price_minor' => 12000,
                'tax_code_ids' => [$taxCode->id],
            ],
        ],
    ];

    $response = $this->postJson('/api/invoices/from-po', $payload);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Invoice created.')
        ->assertJsonPath('data.purchase_order_id', $purchaseOrder->id)
        ->assertJsonPath('data.invoice_number', 'INV-PO-FROM-ENDPOINT');

    $company->refresh();
    expect($company->invoices_monthly_used)->toBe(1);
});

test('invoice creation is blocked when plan invoice cap is exhausted', function (): void {
    [
        'plan' => $plan,
        'company' => $company,
        'finance' => $financeUser,
        'purchaseOrder' => $purchaseOrder,
        'poLines' => $poLines,
        'supplier' => $supplier,
        'taxCode' => $taxCode,
    ] = provisionInvoiceContext(1);

    Notification::fake();

    $company->update([ 'invoices_monthly_used' => $plan->invoices_per_month ]);

    $this->actingAs($financeUser);

    $payload = [
        'supplier_id' => $supplier->id,
        'lines' => [
            [
                'po_line_id' => $poLines[0]->id,
                'description' => 'Precision plates',
                'quantity' => 1,
                'uom' => 'EA',
                'unit_price' => 150,
                'tax_code_ids' => [$taxCode->id],
            ],
        ],
    ];

    $response = $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/invoices", $payload);

    $response->assertStatus(402)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Upgrade required')
        ->assertJsonPath('errors.code', 'invoices_per_month')
        ->assertJsonPath('errors.limit', $plan->invoices_per_month)
        ->assertJsonPath('errors.usage', $plan->invoices_per_month);

    $this->assertDatabaseMissing('invoices', [
        'purchase_order_id' => $purchaseOrder->id,
    ]);
});

test('over-invoicing is rejected via purchase order endpoint', function (): void {
    [
        'finance' => $financeUser,
        'purchaseOrder' => $purchaseOrder,
        'poLines' => $poLines,
        'supplier' => $supplier,
    ] = provisionInvoiceContext();

    $this->actingAs($financeUser);

    $requestedQuantity = (int) $poLines[0]->quantity + 2;
    $expectedMessage = sprintf(
        'Line %d exceeds remaining quantity. Available: %d, requested: %d.',
        $poLines[0]->line_no,
        $poLines[0]->quantity,
        $requestedQuantity,
    );

    $payload = [
        'supplier_id' => $supplier->id,
        'lines' => [
            [
                'po_line_id' => $poLines[0]->id,
                'description' => 'Precision plates',
                'quantity' => $requestedQuantity,
                'uom' => 'EA',
                'unit_price' => 120,
            ],
        ],
    ];

    $response = $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/invoices", $payload);

    $response->assertStatus(422)
        ->assertJsonPath('errors.lines.0', $expectedMessage);

    $this->assertDatabaseMissing('invoices', [
        'purchase_order_id' => $purchaseOrder->id,
    ]);
});

test('over-invoicing is rejected via from-po endpoint', function (): void {
    [
        'finance' => $financeUser,
        'purchaseOrder' => $purchaseOrder,
        'poLines' => $poLines,
        'supplier' => $supplier,
    ] = provisionInvoiceContext();

    $this->actingAs($financeUser);

    $requestedQuantity = (int) $poLines[1]->quantity + 5;
    $expectedMessage = sprintf(
        'Line %d exceeds remaining quantity. Available: %d, requested: %d.',
        $poLines[1]->line_no,
        $poLines[1]->quantity,
        $requestedQuantity,
    );

    $payload = [
        'po_id' => $purchaseOrder->id,
        'supplier_id' => $supplier->id,
        'invoice_number' => 'INV-OVERAGE',
        'lines' => [
            [
                'po_line_id' => $poLines[1]->id,
                'qty_invoiced' => $requestedQuantity,
                'unit_price_minor' => 8000,
            ],
        ],
    ];

    $response = $this->postJson('/api/invoices/from-po', $payload);

    $response->assertStatus(422)
        ->assertJsonPath('errors.lines.0', $expectedMessage);

    $this->assertDatabaseMissing('invoices', [
        'purchase_order_id' => $purchaseOrder->id,
    ]);
});

test('price mismatch recalculates invoice match results and notifies finance team', function (): void {
    [
        'company' => $company,
        'finance' => $financeUser,
        'purchaseOrder' => $purchaseOrder,
        'poLines' => $poLines,
        'supplier' => $supplier,
        'taxCode' => $taxCode,
    ] = provisionInvoiceContext();

    Notification::fake();

    $this->actingAs($financeUser);

    $createPayload = [
        'supplier_id' => $supplier->id,
        'lines' => [
            [
                'po_line_id' => $poLines[0]->id,
                'description' => 'Precision plates',
                'quantity' => 5,
                'uom' => 'EA',
                'unit_price' => 110,
                'tax_code_ids' => [$taxCode->id],
            ],
        ],
    ];

    $createResponse = $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/invoices", $createPayload);

    $createResponse->assertOk();

    $invoiceId = (int) $createResponse->json('data.id');
    $invoiceLineId = (int) $createResponse->json('data.lines.0.id');

    $goodsReceipt = GoodsReceiptNote::create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'number' => 'GRN-'.Str::upper(Str::random(6)),
        'inspected_by_id' => $financeUser->id,
        'inspected_at' => now(),
        'status' => 'complete',
    ]);

    GoodsReceiptLine::create([
        'goods_receipt_note_id' => $goodsReceipt->id,
        'purchase_order_line_id' => $poLines[0]->id,
        'received_qty' => 5,
        'accepted_qty' => 5,
        'rejected_qty' => 0,
    ]);

    $updatePayload = [
        'lines' => [
            [
                'id' => $invoiceLineId,
                'unit_price' => 150,
            ],
        ],
    ];

    $updateResponse = $this->putJson("/api/invoices/{$invoiceId}", $updatePayload);

    $updateResponse->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Invoice updated.')
        ->assertJsonPath('data.match_summary.price_mismatch', 1)
        ->assertJsonPath('data.total', 825);

    $this->assertDatabaseHas('line_taxes', [
        'taxable_id' => $invoiceLineId,
        'taxable_type' => InvoiceLine::class,
        'tax_code_id' => $taxCode->id,
        'amount_minor' => 7500,
    ]);

    $matches = InvoiceMatch::query()->where('invoice_id', $invoiceId)->get();

    expect($matches)->toHaveCount(1);

    $match = $matches->first();
    expect($match->result)->toBe('price_mismatch')
        ->and($match->details['reason'])->toBe('price_difference');

    Notification::assertSentTo(
        $financeUser,
        InvoiceMatchResultNotification::class,
        function (InvoiceMatchResultNotification $notification, array $channels) use ($financeUser, $invoiceId): bool {
            $payload = $notification->toArray($financeUser);

            return $payload['invoice_id'] === $invoiceId
                && $payload['summary']['price_mismatch'] === 1;
        }
    );
});
