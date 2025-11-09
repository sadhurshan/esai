<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\GoodsReceiptLine;
use App\Models\GoodsReceiptNote;
use App\Models\InvoiceMatch;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\InvoiceMatchResultNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

function provisionInvoiceContext(int $invoiceCap = 5): array
{
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
            'quantity' => 3,
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

    return [
        'plan' => $plan,
        'company' => $company,
        'finance' => $financeUser,
        'purchaseOrder' => $purchaseOrder,
        'poLines' => $poLines,
        'supplier' => $supplier,
    ];
}

test('finance user can create invoice and receives match summary', function (): void {
    [
        'company' => $company,
        'finance' => $financeUser,
        'purchaseOrder' => $purchaseOrder,
        'poLines' => $poLines,
        'supplier' => $supplier,
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
            ],
            [
                'po_line_id' => $poLines[1]->id,
                'description' => 'Fastener packs',
                'quantity' => 2,
                'uom' => 'EA',
                'unit_price' => 80,
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

    $results = InvoiceMatch::query()
        ->where('invoice_id', $invoiceId)
        ->pluck('result')
        ->all();

    expect($results)->toHaveCount(2)
        ->and(array_unique($results))->toEqual(['qty_mismatch']);
});

test('invoice creation is blocked when plan invoice cap is exhausted', function (): void {
    [
        'plan' => $plan,
        'company' => $company,
        'finance' => $financeUser,
        'purchaseOrder' => $purchaseOrder,
        'poLines' => $poLines,
        'supplier' => $supplier,
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

test('price mismatch recalculates invoice match results and notifies finance team', function (): void {
    [
        'company' => $company,
        'finance' => $financeUser,
        'purchaseOrder' => $purchaseOrder,
        'poLines' => $poLines,
        'supplier' => $supplier,
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
