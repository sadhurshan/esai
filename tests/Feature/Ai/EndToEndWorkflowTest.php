<?php

use App\Actions\Invoicing\ReviewSupplierInvoiceAction;
use App\Actions\Rfq\AwardLineItemsAction;
use App\Enums\AiChatToolCall;
use App\Enums\InvoiceStatus;
use App\Models\AiActionDraft;
use App\Models\AiChatMessage;
use App\Models\AiChatThread;
use App\Models\AiEvent;
use App\Models\AiWorkflow;
use App\Models\AiWorkflowStep;
use App\Models\Company;
use App\Models\InvoicePayment;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RFQ;
use App\Models\RfqItemAward;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\ChatService;
use App\Services\Ai\Converters\AiDraftConversionService;
use App\Services\Ai\Workflow\AwardQuoteDraftConverter;
use App\Services\Ai\Workflow\PaymentProcessConverter;
use App\Services\Ai\Workflow\PurchaseOrderDraftConverter;
use App\Services\Ai\Workflow\QuoteComparisonDraftConverter;
use App\Services\Ai\Workflow\ReceivingQualityDraftConverter;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Str;

it('runs a procurement workflow end-to-end', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create(['role' => 'buyer_admin']);

    $thread = AiChatThread::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'title' => 'Procurement sprint',
        'status' => AiChatThread::STATUS_OPEN,
        'last_message_at' => now(),
    ]);

    $rfqDraftPayload = [
        'rfq_title' => 'Prototype Widget RFQ',
        'scope_summary' => 'Need 500 CNC widgets with anodized finish before Q3.',
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

    $client = \Mockery::mock(AiClient::class);
    $client->shouldReceive('intentPlan')->once()->andReturn([
        'status' => 'success',
        'message' => 'Planner skipped for feature test.',
        'data' => [],
    ]);
    $client->shouldReceive('chatRespond')->once()->andReturn([
        'status' => 'success',
        'message' => 'Chat response generated.',
        'data' => [
            'response' => [
                'type' => 'draft_action',
                'assistant_message_markdown' => 'Drafted an RFQ for your 500 widget ask.',
                'draft' => [
                    'action_type' => AiActionDraft::TYPE_RFQ_DRAFT,
                    'summary' => 'RFQ outline ready',
                    'payload' => $rfqDraftPayload,
                    'confidence' => 0.86,
                    'needs_human_review' => false,
                ],
                'citations' => [],
                'tool_calls' => [],
                'tool_results' => [],
            ],
            'memory' => [
                'thread_summary' => 'Widgets RFQ context',
            ],
        ],
        'errors' => [],
    ]);

    $client->shouldReceive('helpTool')->once()->andReturn([
        'status' => 'success',
        'message' => 'Help topic generated.',
        'data' => [
            'summary' => 'Plan de acción en español',
            'payload' => [
                'sections' => [
                    ['title' => 'Pasos siguientes', 'body' => '1) Publica el RFQ 2) Evalúa proveedores'],
                ],
                'locale' => 'es-mx',
            ],
            'citations' => ['docs/USER_GUIDE.md#rfq'],
        ],
        'errors' => [],
    ]);

    $client->shouldReceive('chatContinue')->once()->andReturn([
        'status' => 'success',
        'message' => 'Chat response continued.',
        'data' => [
            'response' => [
                'assistant_message_markdown' => 'Te comparto los pasos claves en español.',
                'citations' => [],
                'tool_calls' => [],
                'tool_results' => [],
            ],
            'memory' => [
                'thread_summary' => 'Help call recorded',
            ],
        ],
        'errors' => [],
    ]);

    $this->app->instance(AiClient::class, $client);

    $comparisonAwardStub = \Mockery::mock(AwardLineItemsAction::class);
    $comparisonAwardStub->shouldReceive('execute')->andReturn(collect());

    $quoteComparison = new QuoteComparisonDraftConverter(
        $comparisonAwardStub,
        app(AuditLogger::class)
    );

    /** @var ChatService $chatService */
    $chatService = app(ChatService::class);
    /** @var AiDraftConversionService $draftConversion */
    $draftConversion = app(AiDraftConversionService::class);
    $awardQuote = app(AwardQuoteDraftConverter::class);
    $poConverter = app(PurchaseOrderDraftConverter::class);
    $receivingConverter = app(ReceivingQualityDraftConverter::class);
    $paymentConverter = app(PaymentProcessConverter::class);
    $invoiceReview = app(ReviewSupplierInvoiceAction::class);

    $chatResult = $chatService->sendMessage(
        $thread->fresh(),
        $user->fresh(),
        'Please draft an RFQ for 500 CNC widgets from anodized aluminum.',
        ['locale' => 'en', 'context' => ['ui_mode' => 'workspace']]
    );

    $assistantMessage = $chatResult['assistant_message'];
    expect($assistantMessage->content_text)->toContain('RFQ');

    $draftId = data_get($chatResult, 'response.draft.draft_id');
    expect($draftId)->not->toBeNull();

    $draft = AiActionDraft::query()->findOrFail($draftId);
    $draft->forceFill([
        'status' => AiActionDraft::STATUS_APPROVED,
        'approved_by' => $user->id,
        'approved_at' => now(),
    ])->save();

    $rfq = $draftConversion->convert($draft->fresh(), $user->fresh())['entity'];
    $rfq->refresh();
    expect($rfq->title)->toBe('Prototype Widget RFQ');
    expect($rfq->items)->toHaveCount(1);

    $rfq->status = RFQ::STATUS_OPEN;
    $rfq->save();

    $supplierA = Supplier::factory()->for($company)->create(['name' => 'Acme Precision']);
    $supplierB = Supplier::factory()->for($company)->create(['name' => 'Bravo Fabrication']);

    $quoteA = Quote::factory()
        ->for($company)
        ->for($rfq, 'rfq')
        ->for($supplierA, 'supplier')
        ->create([
            'submitted_by' => $user->id,
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
            'submitted_by' => $user->id,
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

    $workflowId = (string) Str::uuid();

    AiWorkflow::factory()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'workflow_id' => $workflowId,
        'workflow_type' => 'procurement_full_flow',
        'status' => AiWorkflow::STATUS_IN_PROGRESS,
        'steps_json' => ['steps' => []],
    ]);

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
        'workflow_id' => $workflowId,
        'step_index' => 1,
        'action_type' => 'compare_quotes',
        'input_json' => ['rfq_id' => $rfq->id],
        'output_json' => ['payload' => $comparisonPayload],
        'approval_status' => AiWorkflowStep::APPROVAL_APPROVED,
        'approved_by' => $user->id,
    ]);

    $comparisonResult = $quoteComparison->convert($comparisonStep->fresh());
    expect($comparisonResult['rfq_id'])->toBe($rfq->id);
    expect($comparisonResult['shortlisted_quote_ids'])->toContain($quoteA->id);
    expect(RfqItemAward::query()->where('rfq_id', $rfq->id)->count())->toBe(0);

    $rfq->refresh();
    $rfq->status = RFQ::STATUS_OPEN;
    $rfq->save();

    foreach ([$quoteA, $quoteB] as $quote) {
        $quote->refresh()->load('items');
        foreach ($quote->items as $item) {
            $item->status = 'submitted';
            $item->save();
        }
    }

    RfqItemAward::query()->where('rfq_id', $rfq->id)->delete();

    $awardStep = AiWorkflowStep::factory()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflowId,
        'step_index' => 2,
        'action_type' => 'award_quote',
        'input_json' => ['rfq_id' => $rfq->id],
        'output_json' => ['payload' => [
            'rfq_id' => $rfq->id,
            'selected_quote_id' => $quoteA->id,
            'awarded_qty' => 500,
        ]],
        'approval_status' => AiWorkflowStep::APPROVAL_APPROVED,
        'approved_by' => $user->id,
    ]);

    $awardResult = $awardQuote->convert($awardStep->fresh());
    expect($awardResult['supplier_id'])->toBe($supplierA->id);

    $rfqItem = $rfq->items->first();
    $poPayload = [
        'rfq_id' => $rfq->id,
        'po_number' => 'PO-AI-001',
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
        'workflow_id' => $workflowId,
        'step_index' => 3,
        'action_type' => 'po_draft',
        'input_json' => ['rfq_id' => $rfq->id, 'supplier_id' => $supplierA->id],
        'output_json' => ['payload' => $poPayload],
        'approval_status' => AiWorkflowStep::APPROVAL_APPROVED,
        'approved_by' => $user->id,
    ]);

    $poResult = $poConverter->convert($poStep->fresh());
    $purchaseOrder = PurchaseOrder::query()->with('lines')->findOrFail($poResult['purchase_order_id']);
    expect($purchaseOrder->po_number)->toBe('PO-AI-001');
    expect($purchaseOrder->lines)->toHaveCount(1);
    expect(RfqItemAward::query()->where('po_id', $purchaseOrder->id)->count())->toBeGreaterThan(0);

    $receivingPayload = [
        'receipts' => [
            ['receipt_id' => 'GRN-1001'],
            ['receipt_id' => 'GRN-1002'],
        ],
        'quality_findings' => [['issue_code' => 'minor_scratches']],
        'notes' => 'Minor cosmetic scuffs logged.',
    ];

    $receivingStep = AiWorkflowStep::factory()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflowId,
        'step_index' => 4,
        'action_type' => 'receiving_quality',
        'output_json' => ['payload' => $receivingPayload],
        'approval_status' => AiWorkflowStep::APPROVAL_APPROVED,
        'approved_by' => $user->id,
    ]);

    $receivingResult = $receivingConverter->convert($receivingStep->fresh());
    expect($receivingResult['receipts_reviewed'])->toContain('GRN-1001');
    expect($receivingResult['quality_findings'])->toContain('minor_scratches');

    $poLine = $purchaseOrder->lines->first();
    $invoiceDraftPayload = [
        'po_id' => (string) $purchaseOrder->id,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'line_items' => [[
            'description' => $poLine?->description,
            'qty' => (float) ($poLine?->quantity ?? 1),
            'unit_price' => (float) ($poLine?->unit_price ?? 0),
            'tax_rate' => 0.0,
        ]],
        'notes' => 'Invoice generated via Copilot workflow.',
    ];

    $invoiceDraft = AiActionDraft::factory()->approved()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
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
            'summary' => 'Invoice draft ready',
            'citations' => [],
            'confidence' => 0.83,
            'needs_human_review' => false,
        ],
    ]);

    $invoice = $draftConversion->convert($invoiceDraft->fresh(), $user->fresh())['entity'];
    $invoice->refresh();
    expect($invoice->purchase_order_id)->toBe($purchaseOrder->id);

    $invoice->status = InvoiceStatus::Submitted->value;
    $invoice->save();
    $invoiceReview->approve($user->fresh(), $invoice->fresh());
    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Approved->value);

    $approveInvoiceDraft = AiActionDraft::factory()->approved()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'action_type' => AiActionDraft::TYPE_APPROVE_INVOICE,
        'input_json' => [
            'entity_context' => [
                'entity_type' => 'invoice',
                'entity_id' => $invoice->id,
            ],
        ],
        'output_json' => [
            'action_type' => AiActionDraft::TYPE_APPROVE_INVOICE,
            'payload' => [
                'invoice_id' => (string) $invoice->id,
                'payment_reference' => 'PAY-2025-001',
                'note' => 'Paid via ACH',
                'payment_amount' => 12500.0,
                'payment_currency' => 'usd',
                'payment_method' => 'ACH',
                'paid_at' => now()->toDateString(),
            ],
        ],
    ]);

    $draftConversion->convert($approveInvoiceDraft->fresh(), $user->fresh());
    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Paid->value);
    expect(InvoicePayment::query()->where('invoice_id', $invoice->id)->where('payment_reference', 'PAY-2025-001')->exists())->toBeTrue();

    $paymentStep = AiWorkflowStep::factory()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflowId,
        'step_index' => 5,
        'action_type' => 'payment_process',
        'output_json' => ['payload' => [
            'payment_reference' => 'PAY-2025-001',
            'payment_amount' => 12500,
            'currency' => 'usd',
            'note' => 'Funds settled',
        ]],
        'approval_status' => AiWorkflowStep::APPROVAL_APPROVED,
        'approved_by' => $user->id,
    ]);

    $paymentResult = $paymentConverter->convert($paymentStep->fresh());
    expect($paymentResult['payment_reference'])->toBe('PAY-2025-001');
    expect($paymentResult['currency'])->toBe('USD');

    $toolCalls = [[
        'tool_name' => AiChatToolCall::Help->value,
        'call_id' => 'help-1',
        'arguments' => [
            'topic' => 'purchase order follow-up',
            'locale' => 'es-MX',
        ],
    ]];

    $toolResponse = $chatService->resolveTools($thread->fresh(), $user->fresh(), $toolCalls, ['locale' => 'es-MX']);

    expect($toolResponse['assistant_message']->content_text)->toContain('español');
    expect($toolResponse['tool_message']->content_json['tool_results'][0]['result']['summary'])->toBe('Plan de acción en español');

    $assistantMessages = AiChatMessage::query()
        ->where('thread_id', $thread->id)
        ->where('role', AiChatMessage::ROLE_ASSISTANT)
        ->pluck('content_text');

    expect($assistantMessages)->toHaveCount(2);
    expect($assistantMessages->first())->toContain('RFQ');
    expect($assistantMessages->last())->toContain('español');

    expect(AiEvent::query()->where('feature', 'workspace_help')->exists())->toBeTrue();
});

it('creates an item draft end-to-end via the chat planner tool', function (): void {
    config()->set('ai.enabled', true);
    config()->set('ai.shared_secret', 'test-secret');
    config()->set('ai_chat.permissions', []);

    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create(['role' => 'inventory_manager']);

    $thread = AiChatThread::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'title' => 'Item drafting e2e',
        'status' => AiChatThread::STATUS_OPEN,
        'last_message_at' => now(),
        'metadata_json' => [],
    ]);

    $client = \Mockery::mock(AiClient::class);

    $client->shouldReceive('intentPlan')
        ->once()
        ->withArgs(function (array $payload) use ($thread, $user): bool {
            expect($payload['prompt'] ?? null)->toBe('Create item Rotar Blades with hardened steel');
            expect($payload['thread_id'] ?? null)->toBe((string) $thread->id);
            expect($payload['user_id'] ?? null)->toBe($user->id);

            return true;
        })
        ->andReturn([
            'status' => 'success',
            'message' => 'tool selected',
            'data' => [
                'tool' => 'build_item_draft',
                'args' => [
                    'item_code' => 'ROTAR-100',
                    'name' => 'Rotar Blades',
                    'uom' => 'EA',
                ],
            ],
        ]);

    $client->shouldReceive('planAction')
        ->once()
        ->withArgs(function (array $payload): bool {
            expect($payload['action_type'] ?? null)->toBe('item_draft');
            expect($payload['inputs']['item_code'] ?? null)->toBe('ROTAR-100');
            expect($payload['inputs']['name'] ?? null)->toBe('Rotar Blades');

            return true;
        })
        ->andReturn([
            'status' => 'success',
            'message' => 'Draft ready',
            'data' => [
                'action_type' => 'item_draft',
                'summary' => 'Item draft prepared.',
                'payload' => [
                    'item_code' => 'ROTAR-100',
                    'name' => 'Rotar Blades',
                ],
                'citations' => [],
                'warnings' => [],
            ],
        ]);

    $client->shouldNotReceive('chatRespond');

    $this->app->instance(AiClient::class, $client);

    /** @var ChatService $chatService */
    $chatService = app(ChatService::class);

    $result = $chatService->sendMessage(
        $thread->fresh(),
        $user->fresh(),
        'Create item Rotar Blades with hardened steel',
        ['locale' => 'en', 'context' => ['ui_mode' => 'workspace']]
    );

    $draftId = data_get($result, 'response.draft.draft_id');
    expect($draftId)->not->toBeNull();
    expect(data_get($result, 'response.draft.payload.item_code'))->toBe('ROTAR-100');
    expect(data_get($result, 'response.draft.payload.name'))->toBe('Rotar Blades');

    $draft = AiActionDraft::query()->findOrFail($draftId);
    expect($draft->action_type)->toBe(AiActionDraft::TYPE_ITEM_DRAFT);
    expect(data_get($draft->output_json, 'payload.item_code'))->toBe('ROTAR-100');
    expect(data_get($draft->output_json, 'payload.name'))->toBe('Rotar Blades');
    expect($draft->company_id)->toBe($company->id);
});
