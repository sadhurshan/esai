<?php

use App\Actions\Invoicing\ReviewSupplierInvoiceAction;
use App\Actions\Rfq\AwardLineItemsAction;
use App\Enums\InvoiceStatus;
use App\Models\AiActionDraft;
use App\Models\AiWorkflow;
use App\Models\AiWorkflowStep;
use App\Models\Company;
use App\Models\GoodsReceiptNote;
use App\Models\Invoice;
use App\Models\InvoiceMatch;
use App\Models\InvoicePayment;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RFQ;
use App\Models\RfqItemAward;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ai\Converters\AiDraftConversionService;
use App\Services\Ai\Workflow\AwardQuoteDraftConverter;
use App\Services\Ai\Workflow\PurchaseOrderDraftConverter;
use App\Services\Ai\Workflow\QuoteComparisonDraftConverter;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Str;

it('runs the procure to pay workflow and enforces invoice match review gates', function (): void {
    $company = Company::factory()->create();
    $buyer = User::factory()->for($company)->create(['role' => 'buyer_admin']);
    $finance = User::factory()->for($company)->create(['role' => 'finance_admin']);

    $stepsDefinition = collect(config('ai_workflows.templates.procure_to_pay', []))
        ->map(fn (array $step, int $index) => array_merge($step, ['step_index' => $index + 1]))
        ->all();

    $workflow = AiWorkflow::factory()->create([
        'company_id' => $company->id,
        'user_id' => $buyer->id,
        'workflow_id' => (string) Str::uuid(),
        'workflow_type' => 'procure_to_pay',
        'status' => AiWorkflow::STATUS_IN_PROGRESS,
        'current_step' => 1,
        'steps_json' => ['steps' => $stepsDefinition],
    ]);

    /** @var AiDraftConversionService $draftConversion */
    $draftConversion = app(AiDraftConversionService::class);
    $awardQuote = app(AwardQuoteDraftConverter::class);
    $poConverter = app(PurchaseOrderDraftConverter::class);
    $invoiceReview = app(ReviewSupplierInvoiceAction::class);

    $comparisonAwardStub = \Mockery::mock(AwardLineItemsAction::class);
    $comparisonAwardStub->shouldReceive('execute')->andReturn(collect());
    $quoteComparison = new QuoteComparisonDraftConverter(
        $comparisonAwardStub,
        app(AuditLogger::class)
    );

    $rfqDraftPayload = [
        'rfq_title' => 'Procure to Pay Widgets',
        'scope_summary' => 'Need 500 CNC widgets with anodized finish before end of quarter.',
        'line_items' => [[
            'part_id' => 'WIDGET-01',
            'description' => 'CNC machined widget with anodized finish',
            'quantity' => 500,
            'target_date' => now()->addDays(30)->toDateString(),
            'uom' => 'pcs',
        ]],
        'terms_and_conditions' => ['Net 30 terms', 'Supplier provides COC'],
        'questions_for_suppliers' => ['Share best lead time', 'Confirm machining tolerances'],
        'evaluation_rubric' => [
            ['criterion' => 'Price', 'weight' => 0.5, 'guidance' => 'Lower total landed cost'],
            ['criterion' => 'Lead Time', 'weight' => 0.3, 'guidance' => 'Deliver within 30 days'],
            ['criterion' => 'Quality', 'weight' => 0.2, 'guidance' => 'Past scorecards over 90'],
        ],
    ];

    $rfqDraft = AiActionDraft::factory()->approved()->create([
        'company_id' => $company->id,
        'user_id' => $buyer->id,
        'approved_by' => $buyer->id,
        'approved_at' => now(),
        'action_type' => AiActionDraft::TYPE_RFQ_DRAFT,
        'input_json' => ['entity_context' => null, 'inputs' => []],
        'output_json' => [
            'action_type' => AiActionDraft::TYPE_RFQ_DRAFT,
            'payload' => $rfqDraftPayload,
            'summary' => 'RFQ outline ready',
            'citations' => [],
            'confidence' => 0.86,
            'needs_human_review' => false,
        ],
    ]);

    $rfq = $draftConversion->convert($rfqDraft->fresh(), $buyer)['entity'];
    $rfq->refresh();

    expect($rfq->title)->toBe('Procure to Pay Widgets');
    expect($rfq->items)->toHaveCount(1);

    $rfq->status = RFQ::STATUS_OPEN;
    $rfq->save();

    AiWorkflowStep::factory()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflow->workflow_id,
        'step_index' => 1,
        'action_type' => 'rfq_draft',
        'output_json' => ['payload' => ['rfq_id' => $rfq->id]],
        'approval_status' => AiWorkflowStep::APPROVAL_APPROVED,
        'approved_by' => $buyer->id,
        'approved_at' => now(),
    ]);

    $supplierA = Supplier::factory()->for($company)->create(['name' => 'Acme Precision']);
    $supplierB = Supplier::factory()->for($company)->create(['name' => 'Bravo Fabrication']);

    $quoteA = Quote::factory()
        ->for($company)
        ->for($rfq, 'rfq')
        ->for($supplierA, 'supplier')
        ->create([
            'submitted_by' => $buyer->id,
            'status' => 'submitted',
            'currency' => 'USD',
            'total_price' => 12500,
            'total_price_minor' => 1250000,
        ]);

    $quoteB = Quote::factory()
        ->for($company)
        ->for($rfq, 'rfq')
        ->for($supplierB, 'supplier')
        ->create([
            'submitted_by' => $buyer->id,
            'status' => 'submitted',
            'currency' => 'USD',
            'total_price' => 13750,
            'total_price_minor' => 1375000,
        ]);

    foreach ($rfq->items as $rfqItem) {
        QuoteItem::query()->create([
            'company_id' => $company->id,
            'quote_id' => $quoteA->id,
            'rfq_item_id' => $rfqItem->id,
            'unit_price' => 25.0,
            'unit_price_minor' => 2500,
            'currency' => 'USD',
            'lead_time_days' => 21,
        ]);

        QuoteItem::query()->create([
            'company_id' => $company->id,
            'quote_id' => $quoteB->id,
            'rfq_item_id' => $rfqItem->id,
            'unit_price' => 27.5,
            'unit_price_minor' => 2750,
            'currency' => 'USD',
            'lead_time_days' => 28,
        ]);
    }

    $comparisonPayload = [
        'rfq_id' => $rfq->id,
        'rankings' => [
            ['supplier_id' => $supplierA->id, 'score' => 0.92, 'normalized_score' => 0.88, 'notes' => 'Lowest total cost'],
            ['supplier_id' => $supplierB->id, 'score' => 0.81, 'normalized_score' => 0.77, 'notes' => 'Longer lead time'],
        ],
        'recommendation' => $supplierA->id,
    ];

    $comparisonStep = AiWorkflowStep::factory()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflow->workflow_id,
        'step_index' => 2,
        'action_type' => 'compare_quotes',
        'input_json' => ['rfq_id' => $rfq->id],
        'output_json' => ['payload' => $comparisonPayload],
        'approval_status' => AiWorkflowStep::APPROVAL_APPROVED,
        'approved_by' => $buyer->id,
        'approved_at' => now(),
    ]);

    $comparisonResult = $quoteComparison->convert($comparisonStep->fresh());
    expect($comparisonResult['rfq_id'])->toBe($rfq->id);
    expect($comparisonResult['shortlisted_quote_ids'])->toContain($quoteA->id);

    $awardStep = AiWorkflowStep::factory()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflow->workflow_id,
        'step_index' => 3,
        'action_type' => 'award_quote',
        'input_json' => ['rfq_id' => $rfq->id],
        'output_json' => ['payload' => [
            'rfq_id' => $rfq->id,
            'selected_quote_id' => $quoteA->id,
            'awarded_qty' => 500,
        ]],
        'approval_status' => AiWorkflowStep::APPROVAL_APPROVED,
        'approved_by' => $buyer->id,
        'approved_at' => now(),
    ]);

    $awardResult = $awardQuote->convert($awardStep->fresh());
    expect($awardResult['supplier_id'])->toBe($supplierA->id);
    expect(RfqItemAward::query()->where('rfq_id', $rfq->id)->count())->toBeGreaterThan(0);

    $rfqItem = $rfq->items->first();
    $poPayload = [
        'rfq_id' => $rfq->id,
        'po_number' => 'P2P-001',
        'supplier' => [
            'supplier_id' => $supplierA->id,
            'name' => $supplierA->name,
        ],
        'currency' => 'USD',
        'total_value' => 12500,
        'line_items' => [[
            'line_number' => 1,
            'description' => 'CNC Machined Widget',
            'quantity' => 500,
            'unit_price' => 25,
            'uom' => 'pcs',
            'rfq_item_id' => $rfqItem?->id,
            'subtotal' => 12500,
            'delivery_date' => now()->addDays(35)->toDateString(),
        ]],
    ];

    $poStep = AiWorkflowStep::factory()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflow->workflow_id,
        'step_index' => 4,
        'action_type' => 'po_draft',
        'input_json' => ['rfq_id' => $rfq->id, 'supplier_id' => $supplierA->id],
        'output_json' => ['payload' => $poPayload],
        'approval_status' => AiWorkflowStep::APPROVAL_APPROVED,
        'approved_by' => $buyer->id,
        'approved_at' => now(),
    ]);

    $poResult = $poConverter->convert($poStep->fresh());
    $purchaseOrder = PurchaseOrder::query()->with('lines')->findOrFail($poResult['purchase_order_id']);

    expect($purchaseOrder->supplier_id)->toBe($supplierA->id);
    expect($purchaseOrder->lines)->toHaveCount(1);

    $poLine = $purchaseOrder->lines->first();
    expect($poLine?->description)->toBe('CNC Machined Widget');

    $receiptDraft = AiActionDraft::factory()->approved()->create([
        'company_id' => $company->id,
        'user_id' => $buyer->id,
        'approved_by' => $buyer->id,
        'approved_at' => now(),
        'action_type' => AiActionDraft::TYPE_RECEIPT_DRAFT,
        'input_json' => [
            'entity_context' => [
                'entity_type' => 'purchase_order',
                'entity_id' => $purchaseOrder->id,
            ],
            'inputs' => [],
        ],
        'output_json' => [
            'action_type' => AiActionDraft::TYPE_RECEIPT_DRAFT,
            'payload' => [
                'po_id' => (string) $purchaseOrder->id,
                'received_date' => now()->toDateString(),
                'reference' => 'GRN-AUTO-001',
                'status' => 'received_partial',
                'notes' => 'Dock 2 receipt',
                'line_items' => [[
                    'po_line_id' => (string) $poLine?->id,
                    'line_number' => 1,
                    'description' => (string) $poLine?->description,
                    'received_qty' => 490,
                    'accepted_qty' => 480,
                    'rejected_qty' => 10,
                    'issues' => ['10 units dented'],
                    'notes' => '10 units dented',
                ]],
            ],
        ],
    ]);

    $receiptNote = $draftConversion->convert($receiptDraft->fresh(), $buyer)['entity'];
    $receiptNote->load('lines');

    expect($receiptNote)->toBeInstanceOf(GoodsReceiptNote::class);
    expect($receiptNote->purchase_order_id)->toBe($purchaseOrder->id);
    expect($receiptNote->lines)->toHaveCount(1);
    expect($receiptNote->lines->first()->accepted_qty)->toBe(480);

    AiWorkflowStep::factory()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflow->workflow_id,
        'step_index' => 6,
        'action_type' => 'receipt_draft',
        'output_json' => ['payload' => ['receipt_id' => $receiptNote->id]],
        'approval_status' => AiWorkflowStep::APPROVAL_APPROVED,
        'approved_by' => $buyer->id,
        'approved_at' => now(),
    ]);

    $invoiceDraftPayload = [
        'po_id' => (string) $purchaseOrder->id,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'line_items' => [[
            'description' => (string) $poLine?->description,
            'qty' => 500,
            'unit_price' => 25,
            'tax_rate' => 0.0,
        ]],
        'notes' => 'Invoice generated via Copilot workflow.',
    ];

    $invoiceDraft = AiActionDraft::factory()->approved()->create([
        'company_id' => $company->id,
        'user_id' => $finance->id,
        'approved_by' => $finance->id,
        'approved_at' => now(),
        'action_type' => AiActionDraft::TYPE_INVOICE_DRAFT,
        'input_json' => [
            'entity_context' => [
                'entity_type' => 'purchase_order',
                'entity_id' => $purchaseOrder->id,
            ],
            'inputs' => [],
        ],
        'output_json' => [
            'action_type' => AiActionDraft::TYPE_INVOICE_DRAFT,
            'payload' => $invoiceDraftPayload,
        ],
    ]);

    $invoice = $draftConversion->convert($invoiceDraft->fresh(), $finance)['entity'];
    $invoice->refresh();

    expect($invoice->purchase_order_id)->toBe($purchaseOrder->id);

    $invoice->status = InvoiceStatus::Submitted->value;
    $invoice->save();
    $invoiceReview->approve($finance, $invoice->fresh());
    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Approved->value);

    AiWorkflowStep::factory()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflow->workflow_id,
        'step_index' => 7,
        'action_type' => 'invoice_draft',
        'output_json' => ['payload' => ['invoice_id' => $invoice->id]],
        'approval_status' => AiWorkflowStep::APPROVAL_APPROVED,
        'approved_by' => $finance->id,
        'approved_at' => now(),
    ]);

    $invoiceMatchStep = AiWorkflowStep::factory()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflow->workflow_id,
        'step_index' => 8,
        'action_type' => 'invoice_match',
        'output_json' => ['payload' => []],
        'approval_status' => AiWorkflowStep::APPROVAL_PENDING,
    ]);

    $invoiceMatchPayload = [
        'invoice_id' => (string) $invoice->id,
        'matched_po_id' => (string) $purchaseOrder->id,
        'matched_receipt_ids' => [(string) $receiptNote->id],
        'match_score' => 0.83,
        'mismatches' => [[
            'type' => 'qty',
            'line_reference' => 'Line 1',
            'severity' => 'warning',
            'detail' => 'Invoice qty exceeds receipt by 10 units',
            'expected' => 480,
            'actual' => 490,
        ]],
        'recommendation' => [
            'status' => 'hold',
            'explanation' => 'Qty mismatch needs review',
        ],
        'analysis_notes' => ['Qty delta 10 units'],
    ];

    $invoiceMatchDraft = AiActionDraft::factory()->approved()->create([
        'company_id' => $company->id,
        'user_id' => $finance->id,
        'approved_by' => $finance->id,
        'approved_at' => now(),
        'action_type' => AiActionDraft::TYPE_INVOICE_MATCH,
        'input_json' => [
            'entity_context' => [
                'entity_type' => 'invoice',
                'entity_id' => $invoice->id,
            ],
            'inputs' => [],
        ],
        'output_json' => [
            'action_type' => AiActionDraft::TYPE_INVOICE_MATCH,
            'payload' => $invoiceMatchPayload,
        ],
    ]);

    $draftConversion->convert($invoiceMatchDraft->fresh(), $finance);
    $invoice->refresh();

    expect($invoice->matched_status)->toBe('hold');
    expect(InvoiceMatch::query()->where('invoice_id', $invoice->id)->where('result', 'qty_mismatch')->count())->toBe(1);
    expect($invoiceMatchStep->fresh()->isPending())->toBeTrue();

    $resolvedMatchDraft = AiActionDraft::factory()->approved()->create([
        'company_id' => $company->id,
        'user_id' => $finance->id,
        'approved_by' => $finance->id,
        'approved_at' => now(),
        'action_type' => AiActionDraft::TYPE_INVOICE_MATCH,
        'input_json' => [
            'entity_context' => [
                'entity_type' => 'invoice',
                'entity_id' => $invoice->id,
            ],
            'inputs' => [],
        ],
        'output_json' => [
            'action_type' => AiActionDraft::TYPE_INVOICE_MATCH,
            'payload' => [
                'invoice_id' => (string) $invoice->id,
                'matched_po_id' => (string) $purchaseOrder->id,
                'matched_receipt_ids' => [(string) $receiptNote->id],
                'match_score' => 0.98,
                'mismatches' => [[
                    'type' => 'qty',
                    'line_reference' => 'Line 1',
                    'severity' => 'info',
                    'detail' => 'Variance accepted by receiving',
                    'expected' => 480,
                    'actual' => 490,
                ]],
                'recommendation' => [
                    'status' => 'approve',
                    'explanation' => 'Variance signed off by receiving manager',
                ],
                'analysis_notes' => [],
            ],
        ],
    ]);

    $draftConversion->convert($resolvedMatchDraft->fresh(), $finance);
    $invoice->refresh();

    expect($invoice->matched_status)->toBe('matched');
    $finalMatch = InvoiceMatch::query()->where('invoice_id', $invoice->id)->first();
    expect($finalMatch?->result)->toBe('qty_mismatch');
    expect(data_get($finalMatch?->details, 'recommendation.status'))->toBe('approve');

    $invoiceMatchStep->forceFill([
        'approval_status' => AiWorkflowStep::APPROVAL_APPROVED,
        'approved_by' => $finance->id,
        'approved_at' => now(),
    ])->save();

    $paymentDraft = AiActionDraft::factory()->approved()->create([
        'company_id' => $company->id,
        'user_id' => $finance->id,
        'approved_by' => $finance->id,
        'approved_at' => now(),
        'action_type' => AiActionDraft::TYPE_PAYMENT_DRAFT,
        'input_json' => [
            'entity_context' => [
                'entity_type' => 'invoice',
                'entity_id' => $invoice->id,
            ],
            'inputs' => [],
        ],
        'output_json' => [
            'action_type' => AiActionDraft::TYPE_PAYMENT_DRAFT,
            'payload' => [
                'invoice_id' => (string) $invoice->id,
                'amount' => 12500,
                'currency' => 'USD',
                'payment_method' => 'ach',
                'scheduled_date' => now()->addDay()->toDateString(),
                'reference' => 'PAY-P2P-001',
                'notes' => 'Release after match approval',
            ],
        ],
    ]);

    $payment = $draftConversion->convert($paymentDraft->fresh(), $finance)['entity'];
    $payment->refresh();

    expect($payment->invoice_id)->toBe($invoice->id);
    expect($payment->payment_reference)->toBe('PAY-P2P-001');
    expect($invoice->fresh()->payment_reference)->toBe('PAY-P2P-001');

    $paymentStep = AiWorkflowStep::factory()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflow->workflow_id,
        'step_index' => 9,
        'action_type' => 'payment_process',
        'output_json' => ['payload' => [
            'payment_reference' => $payment->payment_reference,
            'payment_amount' => $payment->amount,
            'payment_currency' => $payment->currency,
        ]],
        'approval_status' => AiWorkflowStep::APPROVAL_APPROVED,
        'approved_by' => $finance->id,
        'approved_at' => now(),
    ]);

    expect($paymentStep->isApproved())->toBeTrue();
    expect(InvoicePayment::query()->where('invoice_id', $invoice->id)->count())->toBe(1);

    $workflow->forceFill([
        'current_step' => 9,
        'status' => AiWorkflow::STATUS_COMPLETED,
    ])->save();

    expect($workflow->fresh()->status)->toBe(AiWorkflow::STATUS_COMPLETED);
});
