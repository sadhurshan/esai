<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;

function provisionGoodsReceivingContext(): array
{
    if (! Plan::query()->where('code', 'starter')->exists()) {
        Plan::factory()->create([
            'code' => 'starter',
            'rfqs_per_month' => 25,
            'users_max' => 10,
            'storage_gb' => 5,
        ]);
    }

    $company = Company::factory()->create([
        'plan_code' => 'starter',
        'rfqs_monthly_used' => 0,
        'storage_used_mb' => 0,
    ]);

    $user = User::factory()->for($company)->create();

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'stripe_status' => 'active',
    ]);

    $purchaseOrder = PurchaseOrder::factory()->for($company)->create([
        'status' => 'sent',
    ]);

    $line = PurchaseOrderLine::factory()->for($purchaseOrder)->create([
        'quantity' => 10,
        'received_qty' => 0,
        'receiving_status' => 'open',
    ]);

    return compact('company', 'user', 'purchaseOrder', 'line');
}

test('buyer can record goods receipt and fetch it via nested routes', function (): void {
    [
        'company' => $company,
        'user' => $user,
        'purchaseOrder' => $purchaseOrder,
        'line' => $line,
    ] = provisionGoodsReceivingContext();

    $this->actingAs($user);

    $payload = [
        'number' => 'GRN-'.Str::upper(Str::random(6)),
        'inspected_by_id' => $user->id,
        'inspected_at' => now()->toISOString(),
        'lines' => [
            [
                'purchase_order_line_id' => $line->id,
                'received_qty' => 6,
                'accepted_qty' => 6,
                'rejected_qty' => 0,
            ],
        ],
    ];

    $response = $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/grns", $payload);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.purchase_order_id', $purchaseOrder->id)
        ->assertJsonPath('data.lines.0.purchase_order_line_id', $line->id);

    $noteId = (int) $response->json('data.id');

    $this->assertDatabaseHas('goods_receipt_notes', [
        'id' => $noteId,
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'status' => 'complete',
    ]);

    $this->assertDatabaseHas('goods_receipt_lines', [
        'goods_receipt_note_id' => $noteId,
        'purchase_order_line_id' => $line->id,
        'received_qty' => 6,
        'accepted_qty' => 6,
        'rejected_qty' => 0,
    ]);

    $line->refresh();
    expect($line->received_qty)->toBe(6)
        ->and($line->receiving_status)->toBe('received');

    $index = $this->getJson("/api/purchase-orders/{$purchaseOrder->id}/grns");
    $index->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.items.0.id', $noteId);

    $show = $this->getJson("/api/purchase-orders/{$purchaseOrder->id}/grns/{$noteId}");
    $show->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.id', $noteId);
});

test('validation fails when accepted and rejected quantities do not balance', function (): void {
    [
        'user' => $user,
        'purchaseOrder' => $purchaseOrder,
        'line' => $line,
    ] = provisionGoodsReceivingContext();

    $this->actingAs($user);

    $payload = [
        'number' => 'GRN-MISMATCH',
        'lines' => [
            [
                'purchase_order_line_id' => $line->id,
                'received_qty' => 5,
                'accepted_qty' => 3,
                'rejected_qty' => 1,
            ],
        ],
    ];

    $response = $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/grns", $payload);

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonStructure(['errors' => ['lines.0.rejected_qty']]);
});

test('validation fails when received quantity exceeds remaining open quantity', function (): void {
    [
        'user' => $user,
        'purchaseOrder' => $purchaseOrder,
        'line' => $line,
    ] = provisionGoodsReceivingContext();

    $this->actingAs($user);

    $payload = [
        'number' => 'GRN-OVERFLOW',
        'lines' => [
            [
                'purchase_order_line_id' => $line->id,
                'received_qty' => 11,
                'accepted_qty' => 11,
                'rejected_qty' => 0,
            ],
        ],
    ];

    $response = $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/grns", $payload);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Received quantity exceeds remaining open quantity for the PO line.')
        ->assertJsonStructure(['errors' => ['lines']])
        ->assertJson(fn (AssertableJson $json) => $json
            ->has('errors.lines')
            ->where('errors.lines.0', 'Received quantity exceeds remaining open quantity for the PO line.')
            ->etc()
        );
});

test('rejected quantities mark note and purchase order line as ncr raised', function (): void {
    [
        'user' => $user,
        'purchaseOrder' => $purchaseOrder,
        'line' => $line,
    ] = provisionGoodsReceivingContext();

    $this->actingAs($user);

    $payload = [
        'number' => 'GRN-NCR',
        'lines' => [
            [
                'purchase_order_line_id' => $line->id,
                'received_qty' => 4,
                'accepted_qty' => 1,
                'rejected_qty' => 3,
                'defect_notes' => 'Finish imperfections on surface',
            ],
        ],
    ];

    $response = $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/grns", $payload);

    $response->assertOk()
        ->assertJsonPath('data.status', 'ncr_raised')
        ->assertJsonPath('data.lines.0.rejected_qty', 3);

    $line->refresh();
    expect($line->received_qty)->toBe(4)
        ->and($line->receiving_status)->toBe('ncr_raised');
});

test('buyers cannot create goods receipts for another company purchase order', function (): void {
    [
        'user' => $user,
        'purchaseOrder' => $purchaseOrder,
        'line' => $line,
    ] = provisionGoodsReceivingContext();

    [
        'user' => $otherUser,
    ] = provisionGoodsReceivingContext();

    $this->actingAs($otherUser);

    $payload = [
        'number' => 'GRN-CROSS',
        'lines' => [
            [
                'purchase_order_line_id' => $line->id,
                'received_qty' => 2,
                'accepted_qty' => 2,
                'rejected_qty' => 0,
            ],
        ],
    ];

    $response = $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/grns", $payload);

    $response->assertStatus(404)
        ->assertJsonPath('status', 'error');

    $this->assertDatabaseMissing('goods_receipt_notes', [
        'purchase_order_id' => $purchaseOrder->id,
        'number' => 'GRN-CROSS',
    ]);
});
