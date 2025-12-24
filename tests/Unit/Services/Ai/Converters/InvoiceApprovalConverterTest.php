<?php

use App\Enums\InvoiceStatus;
use App\Models\AiActionDraft;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ai\Converters\InvoiceApprovalConverter;
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

it('marks an approved invoice as paid and records a payment', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $supplier = Supplier::factory()->create(['company_id' => $company->id]);
    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'currency' => 'USD',
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'supplier_id' => $supplier->id,
        'currency' => 'USD',
        'status' => InvoiceStatus::Approved->value,
        'total_minor' => 150_00,
        'invoice_number' => 'INV-9001',
    ]);

    $draft = AiActionDraft::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'action_type' => AiActionDraft::TYPE_APPROVE_INVOICE,
        'status' => AiActionDraft::STATUS_APPROVED,
        'input_json' => [
            'query' => 'Mark invoice paid',
            'inputs' => [
                'invoice_id' => (string) $invoice->id,
            ],
            'entity_context' => [
                'entity_type' => 'invoice',
                'entity_id' => $invoice->id,
            ],
        ],
        'output_json' => [
            'summary' => 'Recommend marking invoice paid.',
            'payload' => [
                'invoice_id' => (string) $invoice->id,
                'payment_reference' => 'WIRE-123',
                'payment_amount' => 1500.25,
                'payment_currency' => 'usd',
                'payment_method' => 'Wire',
                'paid_at' => '2025-12-24',
                'note' => 'Paid via weekly batch.',
            ],
            'citations' => [],
        ],
    ]);

    $converter = app(InvoiceApprovalConverter::class);
    $result = $converter->convert($draft, $user);

    expect($result['entity'])->toBeInstanceOf(Invoice::class);

    $invoice = $invoice->fresh(['payments']);

    expect($invoice->status)->toBe(InvoiceStatus::Paid->value)
        ->and($invoice->payment_reference)->toBe('WIRE-123')
        ->and($invoice->review_note)->toBe('Paid via weekly batch.')
        ->and($invoice->payments)->toHaveCount(1);

    $payment = $invoice->payments->first();

    expect($payment->currency)->toBe('USD')
        ->and((string) $payment->amount)->toBe('1500.2500')
        ->and($payment->amount_minor)->toBe(150025)
        ->and($payment->payment_reference)->toBe('WIRE-123')
        ->and($payment->payment_method)->toBe('Wire');

    $draft->refresh();

    expect($draft->entity_id)->toBe($invoice->id)
        ->and($draft->entity_type)->toBe($invoice->getMorphClass());
});
