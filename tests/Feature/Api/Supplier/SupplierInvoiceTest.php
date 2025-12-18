<?php

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

/**
 * @return array{
 *     buyer: \App\Models\Company,
 *     supplier_user: User,
 *     supplier_persona: array<string, mixed>,
 *     supplier_company: \App\Models\Company,
 *     supplier: \App\Models\Supplier,
 *     purchase_order: PurchaseOrder,
 *     po_line: PurchaseOrderLine
 * }
 */
function createSupplierInvoiceTestContext(): array
{
    $buyer = createSubscribedCompany([
        'status' => 'active',
    ], [
        'code' => 'supplier-invoice-'.Str::lower(Str::random(6)),
        'supplier_invoicing_enabled' => true,
        'invoices_per_month' => 100,
    ]);

    $supplierContext = createSupplierPersonaForBuyer($buyer);

    $purchaseOrder = PurchaseOrder::factory()
        ->for($buyer, 'company')
        ->create([
            'supplier_id' => $supplierContext['supplier']->id,
            'status' => 'sent',
            'po_number' => 'PO-'.Str::upper(Str::random(6)),
        ]);

    $poLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'line_no' => 10,
        'quantity' => 5,
        'unit_price' => 125,
        'uom' => 'EA',
    ]);

    return [
        'buyer' => $buyer,
        'supplier_user' => $supplierContext['user'],
        'supplier_persona' => $supplierContext['persona'],
        'supplier_company' => $supplierContext['supplier_company'],
        'supplier' => $supplierContext['supplier'],
        'purchase_order' => $purchaseOrder,
        'po_line' => $poLine,
    ];
}

test('supplier personas can author and submit invoices with audit logging', function (): void {
    Mail::fake();

    $context = createSupplierInvoiceTestContext();

    expect($context['supplier']->company_id)->toBe($context['supplier_company']->id);

    actingAs($context['supplier_user']);

    $payload = [
        'invoice_number' => 'SUP-INV-1001',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'lines' => [
            [
                'po_line_id' => $context['po_line']->id,
                'quantity' => 2,
                'unit_price' => 150.5,
                'description' => 'Precision fixtures',
                'uom' => 'EA',
            ],
        ],
    ];

    $response = postJson(
        "/api/supplier/purchase-orders/{$context['purchase_order']->id}/invoices",
        $payload,
        ['X-Active-Persona' => $context['supplier_persona']['key']]
    );

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Invoice draft created.')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.created_by_type', 'supplier')
        ->assertJsonPath('data.supplier_company_id', $context['supplier_company']->id);

    $invoiceId = (int) $response->json('data.id');

    $submit = postJson(
        "/api/supplier/invoices/{$invoiceId}/submit",
        ['note' => 'Ready for review'],
        ['X-Active-Persona' => $context['supplier_persona']['key']]
    );

    $submit->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Invoice submitted.')
        ->assertJsonPath('data.status', 'submitted');

    $this->assertDatabaseHas('invoices', [
        'id' => $invoiceId,
        'created_by_type' => 'supplier',
        'supplier_company_id' => $context['supplier_company']->id,
        'status' => 'submitted',
    ]);

    CompanyContext::forCompany($context['buyer']->id, function () use ($context, $invoiceId): void {
        $log = AuditLog::query()
            ->where('entity_type', Invoice::class)
            ->where('entity_id', $invoiceId)
            ->where('before->event', 'invoice_submitted')
            ->first();

        expect($log)->not()->toBeNull();
        expect($log->persona_type)->toBe('supplier');
        expect($log->acting_supplier_id)->toBe($context['supplier']->id);
    });
});

test('supplier personas cannot invoice purchase orders outside their buyer workspace', function (): void {
    Mail::fake();

    $context = createSupplierInvoiceTestContext();

    $otherBuyer = createSubscribedCompany([
        'status' => 'active',
    ], [
        'code' => 'supplier-invoice-'.Str::lower(Str::random(6)),
        'supplier_invoicing_enabled' => true,
    ]);

    $foreignPo = PurchaseOrder::factory()->for($otherBuyer, 'company')->create([
        'supplier_id' => $context['supplier']->id,
        'status' => 'sent',
    ]);

    $foreignLine = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $foreignPo->id,
        'line_no' => 20,
        'quantity' => 4,
        'unit_price' => 110,
        'uom' => 'EA',
    ]);

    actingAs($context['supplier_user']);

    $payload = [
        'invoice_number' => 'SUP-INV-2002',
        'invoice_date' => now()->toDateString(),
        'lines' => [
            [
                'po_line_id' => $foreignLine->id,
                'quantity' => 1,
                'unit_price' => 100,
            ],
        ],
    ];

    postJson(
        "/api/supplier/purchase-orders/{$foreignPo->id}/invoices",
        $payload,
        ['X-Active-Persona' => $context['supplier_persona']['key']]
    )
        ->assertStatus(404)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Purchase order not found.');
});

test('buyers can approve supplier invoices and emit audit events', function (): void {
    Mail::fake();

    $context = createSupplierInvoiceTestContext();

    actingAs($context['supplier_user']);

    $payload = [
        'invoice_number' => 'SUP-INV-3003',
        'invoice_date' => now()->toDateString(),
        'lines' => [
            [
                'po_line_id' => $context['po_line']->id,
                'quantity' => 2,
                'unit_price' => 200,
            ],
        ],
    ];

    $storeResponse = postJson(
        "/api/supplier/purchase-orders/{$context['purchase_order']->id}/invoices",
        $payload,
        ['X-Active-Persona' => $context['supplier_persona']['key']]
    );

    $storeResponse->assertOk();

    $invoiceId = (int) $storeResponse->json('data.id');

    postJson(
        "/api/supplier/invoices/{$invoiceId}/submit",
        ['note' => 'Submitted for approval'],
        ['X-Active-Persona' => $context['supplier_persona']['key']]
    )->assertOk();

    $financeUser = User::factory()->for($context['buyer'])->create([
        'role' => 'finance',
    ]);

    actingAs($financeUser);

    postJson(
        "/api/invoices/{$invoiceId}/review/approve",
        ['note' => 'Looks good']
    )
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Invoice approved.')
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.reviewed_by.id', $financeUser->id);

    $this->assertDatabaseHas('invoices', [
        'id' => $invoiceId,
        'status' => 'approved',
        'reviewed_by_id' => $financeUser->id,
    ]);

    CompanyContext::forCompany($context['buyer']->id, function () use ($invoiceId, $financeUser): void {
        $log = AuditLog::query()
            ->where('entity_type', Invoice::class)
            ->where('entity_id', $invoiceId)
            ->where('before->event', 'invoice_approved')
            ->first();

        expect($log)->not()->toBeNull();
        expect($log->persona_type)->toBe('buyer');
        expect($log->user_id)->toBe($financeUser->id);
    });
});

test('buyers can reject supplier invoices with audit context', function (): void {
    Mail::fake();

    $context = createSupplierInvoiceTestContext();

    actingAs($context['supplier_user']);

    $payload = [
        'invoice_number' => 'SUP-INV-4004',
        'invoice_date' => now()->toDateString(),
        'lines' => [
            [
                'po_line_id' => $context['po_line']->id,
                'quantity' => 3,
                'unit_price' => 180,
            ],
        ],
    ];

    $storeResponse = postJson(
        "/api/supplier/purchase-orders/{$context['purchase_order']->id}/invoices",
        $payload,
        ['X-Active-Persona' => $context['supplier_persona']['key']]
    );

    $storeResponse->assertOk();

    $invoiceId = (int) $storeResponse->json('data.id');

    postJson(
        "/api/supplier/invoices/{$invoiceId}/submit",
        ['note' => 'Submitting for buyer review'],
        ['X-Active-Persona' => $context['supplier_persona']['key']]
    )->assertOk();

    $financeUser = User::factory()->for($context['buyer'])->create([
        'role' => 'finance',
    ]);

    actingAs($financeUser);

    $note = 'Quantity mismatch—please revise.';

    postJson(
        "/api/invoices/{$invoiceId}/review/reject",
        ['note' => $note]
    )
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Invoice rejected.')
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.review_note', $note);

    $this->assertDatabaseHas('invoices', [
        'id' => $invoiceId,
        'status' => 'rejected',
        'review_note' => $note,
    ]);

    CompanyContext::forCompany($context['buyer']->id, function () use ($invoiceId, $financeUser): void {
        $log = AuditLog::query()
            ->where('entity_type', Invoice::class)
            ->where('entity_id', $invoiceId)
            ->where('before->event', 'invoice_rejected')
            ->first();

        expect($log)->not()->toBeNull();
        expect($log->persona_type)->toBe('buyer');
        expect($log->user_id)->toBe($financeUser->id);
    });
});
