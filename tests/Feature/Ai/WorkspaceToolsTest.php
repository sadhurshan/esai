<?php

use App\Enums\InvoiceStatus;
use App\Enums\RfqItemAwardStatus;
use App\Enums\RiskGrade;
use App\Models\AiApprovalRequest;
use App\Models\AiWorkflow;
use App\Models\AiWorkflowStep;
use App\Models\Company;
use App\Models\GoodsReceiptNote;
use App\Models\Inventory;
use App\Models\InventorySetting;
use App\Models\Invoice;
use App\Models\InvoiceDisputeTask;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RFQ;
use App\Models\RfqItem;
use App\Models\RfqItemAward;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\SupplierRiskScore;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Ai\AiClient;
use App\Services\Ai\WorkspaceToolResolver;
use App\Services\Ai\WorkflowService;

it('returns placeholder receipts payloads for workspace.get_receipts', function () {
    $company = Company::factory()->create();
    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.get_receipts',
        'call_id' => 'call-receipts',
        'arguments' => [
            'context' => ['origin' => 'spec-test'],
            'filters' => ['supplier_name' => 'Helios Industries'],
            'limit' => 2,
        ],
    ]]);

    expect($results)->toHaveCount(1)
        ->and($results[0]['tool_name'])->toBe('workspace.get_receipts');

    $payload = $results[0]['result'];

    expect($payload)->toBeArray()
        ->and($payload['items'])->toHaveCount(2)
        ->and($payload['items'][0])->toHaveKeys([
            'id',
            'receipt_number',
            'supplier_name',
            'status',
            'total_amount',
            'created_at',
        ])
        ->and($payload['items'][0]['created_at'])->toBeString()
        ->and($payload['items'][0]['total_amount'])->toBeFloat();

    expect($payload['meta']['filters'])->toMatchArray(['supplier_name' => 'Helios Industries'])
        ->and($payload['meta']['context'])->toMatchArray(['origin' => 'spec-test']);
});

it('returns placeholder invoice payloads for workspace.get_invoices', function () {
    $company = Company::factory()->create();
    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.get_invoices',
        'call_id' => 'call-invoices',
        'arguments' => [
            'context' => ['origin' => 'spec-test'],
            'filters' => ['supplier_name' => 'Helios Industries'],
            'limit' => 3,
        ],
    ]]);

    expect($results)->toHaveCount(1)
        ->and($results[0]['tool_name'])->toBe('workspace.get_invoices');

    $payload = $results[0]['result'];

    expect($payload)->toBeArray()
        ->and($payload['items'])->toHaveCount(3)
        ->and($payload['items'][0])->toHaveKeys([
            'id',
            'invoice_number',
            'supplier_name',
            'status',
            'total_amount',
            'created_at',
        ])
        ->and($payload['items'][0]['created_at'])->toBeString()
        ->and($payload['items'][0]['total_amount'])->toBeFloat();

    expect($payload['meta']['filters'])->toMatchArray(['supplier_name' => 'Helios Industries'])
        ->and($payload['meta']['context'])->toMatchArray(['origin' => 'spec-test']);
});

it('delegates workspace.help to the AI help tool', function (): void {
    $company = Company::factory()->create();

    $client = \Mockery::mock(AiClient::class);
    $client->shouldReceive('helpTool')
        ->once()
        ->with(\Mockery::on(function (array $payload) use ($company): bool {
            expect($payload['company_id'] ?? null)->toBe($company->id);
            expect($payload['inputs']['topic'] ?? null)->toBe('Approve invoice');
            expect($payload['inputs']['module'] ?? null)->toBe('invoice');

            return true;
        }))
        ->andReturn([
            'status' => 'success',
            'message' => 'Workspace help guide generated.',
            'data' => [
                'summary' => 'Guided steps ready.',
                'payload' => ['topic' => 'approve invoice'],
                'citations' => [['doc_id' => 'doc-1']],
            ],
            'errors' => [],
        ]);

    $this->app->instance(AiClient::class, $client);

    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.help',
        'call_id' => 'call-help',
        'arguments' => [
            'topic' => 'Approve invoice',
            'module' => 'invoice',
            'entity_id' => 'INV-45',
            'context' => [['doc_id' => 'doc-1']],
        ],
    ]]);

    expect($results)->toHaveCount(1)
        ->and($results[0]['tool_name'])->toBe('workspace.help');

    $payload = $results[0]['result'];

    expect($payload)->toMatchArray([
        'summary' => 'Guided steps ready.',
        'payload' => ['topic' => 'approve invoice'],
        'citations' => [['doc_id' => 'doc-1']],
    ]);

    expect($payload['cta'])->toMatchArray([
        'module' => 'invoice',
        'action' => 'detail',
        'url' => '/app/invoices/INV-45',
    ]);
});

it('maps workspace.navigate to module listings', function (): void {
    $company = Company::factory()->create();
    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.navigate',
        'call_id' => 'nav-list',
        'arguments' => [
            'module' => 'Suppliers',
            'action' => 'list',
        ],
    ]]);

    $payload = $results[0]['result'];

    expect($payload)->toMatchArray([
        'module' => 'supplier',
        'action' => 'list',
        'url' => '/app/suppliers',
        'label' => 'Supplier Directory',
    ]);

    expect($payload['breadcrumbs'])->toHaveCount(2)
        ->and($payload['breadcrumbs'][0]['url'])->toBe('/app');
});

it('builds entity deep links via workspace.navigate', function (): void {
    $company = Company::factory()->create();
    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.navigate',
        'call_id' => 'nav-detail',
        'arguments' => [
            'module' => 'purchase_orders',
            'entity_id' => 'PO-100',
        ],
    ]]);

    $payload = $results[0]['result'];

    expect($payload)->toMatchArray([
        'module' => 'po',
        'action' => 'detail',
        'url' => '/app/purchase-orders/PO-100',
        'label' => 'PO PO-100',
    ]);

    expect($payload['breadcrumbs'])->toHaveCount(3)
        ->and($payload['breadcrumbs'][2]['label'])->toBe('PO PO-100');
});

it('returns actionable recommendations via workspace.next_best_action', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'finance_admin',
    ]);

    $purchaseOrder = PurchaseOrder::factory()->for($company)->create([
        'status' => 'sent',
        'expected_at' => now()->subDays(2),
    ]);

    $workflow = AiWorkflow::factory()->create([
        'company_id' => $company->id,
    ]);

    $workflowStep = AiWorkflowStep::factory()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflow->workflow_id,
    ]);

    AiApprovalRequest::query()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflow->workflow_id,
        'workflow_step_id' => $workflowStep->id,
        'step_index' => 1,
        'entity_type' => 'purchase_order',
        'entity_id' => (string) $purchaseOrder->id,
        'step_type' => 'release',
        'approver_role' => 'finance_admin',
        'approver_user_id' => $user->id,
        'requested_by' => $user->id,
        'message' => 'Approve PO release.',
        'status' => AiApprovalRequest::STATUS_PENDING,
    ]);

    Invoice::factory()->for($company)->create([
        'purchase_order_id' => $purchaseOrder->id,
        'invoice_number' => 'INV-2001',
        'status' => InvoiceStatus::BuyerReview->value,
        'due_date' => now()->addDays(3),
    ]);

    GoodsReceiptNote::factory()->for($company)->create([
        'purchase_order_id' => $purchaseOrder->id,
        'status' => 'pending',
    ]);

    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.next_best_action',
        'call_id' => 'nba-primary',
        'arguments' => [
            'user_id' => $user->id,
        ],
    ]]);

    $payload = $results[0]['result'];
    $recommendations = collect($payload['recommendations']);

    expect($recommendations->count())->toBeGreaterThanOrEqual(3)
        ->and($recommendations->count())->toBeLessThanOrEqual(5);

    $approvalRec = $recommendations->firstWhere('title', 'Review pending approvals');
    $poRec = $recommendations->firstWhere('title', 'Expedite overdue purchase orders');
    $invoiceRec = $recommendations->firstWhere('title', 'Unblock invoices under review');

    expect($approvalRec)->not()->toBeNull()
        ->and($approvalRec['data']['count'])->toBe(1)
        ->and($approvalRec['link']['url'])->toBe('/app/purchase-orders');

    expect($poRec)->not()->toBeNull()
        ->and($poRec['data']['count'])->toBe(1)
        ->and($poRec['link']['url'])->toBe('/app/purchase-orders');

    expect($invoiceRec)->not()->toBeNull()
        ->and($invoiceRec['data']['count'])->toBe(1)
        ->and($invoiceRec['link']['url'])->toBe('/app/invoices');
});

it('prioritizes context entities for workspace.next_best_action', function (): void {
    $company = Company::factory()->create();
    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.next_best_action',
        'call_id' => 'nba-context',
        'arguments' => [
            'context_entity' => [
                'type' => 'invoice',
                'entity_id' => 'INV-55',
            ],
        ],
    ]]);

    $payload = $results[0]['result'];
    $recommendations = collect($payload['recommendations']);

    $contextRec = $recommendations->firstWhere('title', 'Continue with INVOICE INV-55');

    expect($contextRec)->not()->toBeNull()
        ->and($contextRec['link']['url'])->toBe('/app/invoices/INV-55');
});

it('searches item master records via workspace.search_items', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    $matchingPart = Part::factory()->for($company)->create([
        'part_number' => 'ROT-100',
        'name' => 'Rotor Blade Assembly',
        'description' => 'Rotor assembly for compressors',
        'category' => 'Machining',
        'uom' => 'ea',
        'active' => true,
    ]);

    Part::factory()->for($company)->create([
        'part_number' => 'ROT-200',
        'name' => 'Rotor Housing',
        'description' => 'Rotor housing variant',
        'category' => 'Machining',
        'uom' => 'ea',
        'active' => false,
    ]);

    Part::factory()->for($otherCompany)->create([
        'part_number' => 'ROT-999',
        'name' => 'Foreign Rotor',
        'description' => 'Different tenant rotor',
        'category' => 'Machining',
        'uom' => 'ea',
        'active' => true,
    ]);

    Part::factory()->for($company)->create([
        'part_number' => 'BOLT-100',
        'name' => 'Bolt Kit',
        'description' => 'Fastener set',
        'category' => 'Fasteners',
        'uom' => 'ea',
        'active' => true,
    ]);

    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.search_items',
        'call_id' => 'call-items',
        'arguments' => [
            'query' => 'Rotor',
            'statuses' => ['active'],
            'categories' => ['Machining'],
            'uom' => 'ea',
            'limit' => 5,
        ],
    ]]);

    expect($results)->toHaveCount(1)
        ->and($results[0]['tool_name'])->toBe('workspace.search_items');

    $payload = $results[0]['result'];

    expect($payload['items'])->toHaveCount(1)
        ->and($payload['items'][0])->toMatchArray([
            'part_id' => $matchingPart->id,
            'part_number' => 'ROT-100',
            'name' => 'Rotor Blade Assembly',
            'category' => 'Machining',
            'uom' => 'ea',
            'status' => 'active',
        ]);

    expect($payload['meta'])->toMatchArray([
        'limit' => 5,
        'query' => 'Rotor',
        'statuses' => ['active'],
        'categories' => ['Machining'],
        'uom' => 'ea',
        'total_count' => 1,
    ]);

    expect($payload['meta']['status_counts'])->toMatchArray([
        'active' => 1,
        'inactive' => 0,
    ]);
});

it('gets an item with inventory, supplier history, and last purchase data via workspace.get_item', function (): void {
    $company = Company::factory()->create();
    $warehousePrimary = Warehouse::factory()->for($company)->create(['code' => 'WH-PRI']);
    $warehouseSecondary = Warehouse::factory()->for($company)->create(['code' => 'WH-SEC']);

    $part = Part::factory()->for($company)->create([
        'part_number' => 'ROT-500',
        'name' => 'Rotor Blisk',
        'description' => 'Precision machined rotor blisk',
        'category' => 'Machining',
        'uom' => 'ea',
        'spec' => 'AMS-4928',
        'attributes' => ['weight_kg' => 1.2, 'material' => 'Ti-6Al4V'],
        'active' => true,
    ]);

    Inventory::query()->create([
        'company_id' => $company->id,
        'part_id' => $part->id,
        'warehouse_id' => $warehousePrimary->id,
        'bin_id' => null,
        'on_hand' => 5.5,
        'allocated' => 1.0,
        'on_order' => 2.0,
        'uom' => 'ea',
    ]);

    Inventory::query()->create([
        'company_id' => $company->id,
        'part_id' => $part->id,
        'warehouse_id' => $warehouseSecondary->id,
        'bin_id' => null,
        'on_hand' => 3.25,
        'allocated' => 0.5,
        'on_order' => 4.0,
        'uom' => 'ea',
    ]);

    InventorySetting::query()->create([
        'company_id' => $company->id,
        'part_id' => $part->id,
        'min_qty' => 5,
        'max_qty' => 25,
        'safety_stock' => 4,
        'reorder_qty' => 10,
    ]);

    $supplierPrimary = Supplier::factory()->create([
        'company_id' => $company->id,
        'name' => 'Aero Works',
        'city' => 'Austin',
        'country' => 'US',
    ]);

    $supplierSecondary = Supplier::factory()->create([
        'company_id' => $company->id,
        'name' => 'Beta Machining',
        'city' => 'Dallas',
        'country' => 'US',
    ]);

    $rfq = RFQ::factory()->create(['company_id' => $company->id]);

    $rfqItemPrimary = RfqItem::factory()->create([
        'rfq_id' => $rfq->id,
        'company_id' => $company->id,
        'part_number' => $part->part_number,
        'description' => 'Rotor blisk spec sheet',
    ]);

    $rfqItemPrimaryAlt = RfqItem::factory()->create([
        'rfq_id' => $rfq->id,
        'company_id' => $company->id,
        'part_number' => $part->part_number,
        'description' => 'Rotor blisk spec revision',
    ]);

    $rfqItemSecondary = RfqItem::factory()->create([
        'rfq_id' => $rfq->id,
        'company_id' => $company->id,
        'part_number' => $part->part_number,
        'description' => 'Rotor blisk spare lot',
    ]);

    $awarder = User::factory()->create(['company_id' => $company->id]);

    $quotePrimary = Quote::factory()
        ->for($company)
        ->for($rfq)
        ->for($supplierPrimary, 'supplier')
        ->create([
            'submitted_by' => $awarder->id,
            'status' => 'submitted',
            'currency' => 'USD',
        ]);

    $quoteSecondary = Quote::factory()
        ->for($company)
        ->for($rfq)
        ->for($supplierSecondary, 'supplier')
        ->create([
            'submitted_by' => $awarder->id,
            'status' => 'submitted',
            'currency' => 'USD',
        ]);

    $quoteItemPrimaryMain = QuoteItem::query()->create([
        'quote_id' => $quotePrimary->id,
        'company_id' => $company->id,
        'rfq_item_id' => $rfqItemPrimary->id,
        'unit_price' => 150,
        'unit_price_minor' => 15000,
        'currency' => 'USD',
        'lead_time_days' => 20,
        'status' => 'submitted',
    ]);

    $quoteItemPrimaryAlt = QuoteItem::query()->create([
        'quote_id' => $quotePrimary->id,
        'company_id' => $company->id,
        'rfq_item_id' => $rfqItemPrimaryAlt->id,
        'unit_price' => 152,
        'unit_price_minor' => 15200,
        'currency' => 'USD',
        'lead_time_days' => 22,
        'status' => 'submitted',
    ]);

    $quoteItemSecondary = QuoteItem::query()->create([
        'quote_id' => $quoteSecondary->id,
        'company_id' => $company->id,
        'rfq_item_id' => $rfqItemSecondary->id,
        'unit_price' => 160,
        'unit_price_minor' => 16000,
        'currency' => 'USD',
        'lead_time_days' => 18,
        'status' => 'submitted',
    ]);

    $awardPrimaryOld = RfqItemAward::query()->create([
        'company_id' => $company->id,
        'rfq_id' => $rfq->id,
        'rfq_item_id' => $rfqItemPrimary->id,
        'supplier_id' => $supplierPrimary->id,
        'quote_id' => $quotePrimary->id,
        'quote_item_id' => $quoteItemPrimaryMain->id,
        'awarded_by' => $awarder->id,
        'awarded_qty' => 8,
        'awarded_at' => now()->subDays(5),
        'status' => RfqItemAwardStatus::Awarded,
    ]);

    $awardPrimaryRecent = RfqItemAward::query()->create([
        'company_id' => $company->id,
        'rfq_id' => $rfq->id,
        'rfq_item_id' => $rfqItemPrimaryAlt->id,
        'supplier_id' => $supplierPrimary->id,
        'quote_id' => $quotePrimary->id,
        'quote_item_id' => $quoteItemPrimaryAlt->id,
        'awarded_by' => $awarder->id,
        'awarded_qty' => 12,
        'awarded_at' => now()->subDay(),
        'status' => RfqItemAwardStatus::Awarded,
    ]);

    RfqItemAward::query()->create([
        'company_id' => $company->id,
        'rfq_id' => $rfq->id,
        'rfq_item_id' => $rfqItemSecondary->id,
        'supplier_id' => $supplierSecondary->id,
        'quote_id' => $quoteSecondary->id,
        'quote_item_id' => $quoteItemSecondary->id,
        'awarded_by' => $awarder->id,
        'awarded_qty' => 6,
        'awarded_at' => now()->subDays(2),
        'status' => RfqItemAwardStatus::Awarded,
    ]);

    $purchaseOrder = PurchaseOrder::factory()
        ->for($company)
        ->create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplierPrimary->id,
            'po_number' => 'PO-ROT-500',
            'status' => 'approved',
            'currency' => 'USD',
            'ordered_at' => now()->subHours(6),
        ]);

    PurchaseOrderLine::factory()
        ->for($purchaseOrder)
        ->create([
            'rfq_item_id' => $rfqItemPrimaryAlt->id,
            'rfq_item_award_id' => $awardPrimaryRecent->id,
            'description' => 'Rotor blisk lot',
            'quantity' => 25,
            'uom' => 'EA',
            'unit_price' => 155.75,
            'currency' => 'USD',
        ]);

    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.get_item',
        'call_id' => 'call-item',
        'arguments' => ['item_id' => $part->id],
    ]]);

    expect($results)->toHaveCount(1)
        ->and($results[0]['tool_name'])->toBe('workspace.get_item');

    $item = $results[0]['result']['item'];

    expect($item['part_id'])->toBe($part->id)
        ->and($item['status'])->toBe('active')
        ->and($item['spec'])->toBe('AMS-4928')
        ->and($item['attributes'])->toMatchArray(['weight_kg' => 1.2, 'material' => 'Ti-6Al4V']);

    expect($item['inventory']['on_hand'])->toEqualWithDelta(8.75, 0.0001)
        ->and($item['inventory']['allocated'])->toEqualWithDelta(1.5, 0.0001)
        ->and($item['inventory']['on_order'])->toEqualWithDelta(6.0, 0.0001);

    expect($item['settings'])->not()->toBeNull();
    expect((float) $item['settings']['min_qty'])->toEqual(5.0)
        ->and((float) $item['settings']['reorder_qty'])->toEqual(10.0);

    expect($item['preferred_suppliers'])->toHaveCount(2)
        ->and($item['preferred_suppliers'][0]['supplier_id'])->toBe($supplierPrimary->id)
        ->and($item['preferred_suppliers'][0]['awards_count'])->toBe(2)
        ->and($item['preferred_suppliers'][0]['location'])->toBe('Austin, US')
        ->and($item['preferred_suppliers'][1]['supplier_id'])->toBe($supplierSecondary->id)
        ->and($item['preferred_suppliers'][1]['location'])->toBe('Dallas, US');

    $lastPurchase = $item['last_purchase'];

    expect($lastPurchase)->not()->toBeNull()
        ->and($lastPurchase['po_id'])->toBe($purchaseOrder->id)
        ->and($lastPurchase['po_number'])->toBe('PO-ROT-500')
        ->and($lastPurchase['quantity'])->toBe(25)
        ->and($lastPurchase['currency'])->toBe('USD')
        ->and($lastPurchase['unit_price'])->toEqual(155.75)
        ->and($lastPurchase['supplier'])->toMatchArray([
            'supplier_id' => $supplierPrimary->id,
            'name' => $supplierPrimary->name,
            'location' => 'Austin, US',
        ])
        ->and($lastPurchase['ordered_at'])->not()->toBeNull();
});

it('searches supplier directories via workspace.search_suppliers', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    $matchingSupplier = Supplier::factory()->create([
        'company_id' => $company->id,
        'name' => 'Precision Dynamics',
        'status' => 'approved',
        'lead_time_days' => 12,
        'rating_avg' => 4.25,
        'risk_grade' => RiskGrade::Low,
        'city' => 'Austin',
        'country' => 'US',
        'verified_at' => now()->subDay(),
        'capabilities' => [
            'methods' => ['CNC Milling', 'Waterjet Cutting'],
            'materials' => ['Titanium', 'Aluminum 7075'],
            'finishes' => ['Anodizing'],
            'tolerances' => ['ISO 2768-m'],
            'industries' => ['Aerospace'],
        ],
    ]);

    SupplierRiskScore::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $matchingSupplier->id,
        'overall_score' => 0.87,
    ]);

    SupplierDocument::factory()->for($matchingSupplier, 'supplier')->create([
        'company_id' => $company->id,
        'type' => 'iso9001',
        'status' => 'valid',
    ]);

    Supplier::factory()->create([
        'company_id' => $company->id,
        'name' => 'Pending Fabrication',
        'status' => 'pending',
        'capabilities' => ['methods' => ['Casting']],
    ]);

    Supplier::factory()->create([
        'company_id' => $otherCompany->id,
        'name' => 'Off Tenant Supplier',
        'status' => 'approved',
    ]);

    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.search_suppliers',
        'call_id' => 'call-suppliers',
        'arguments' => [
            'query' => 'Precision',
            'statuses' => ['approved'],
            'methods' => ['CNC Milling'],
            'materials' => ['Titanium'],
            'certifications' => ['iso9001'],
            'rating_min' => 4,
            'lead_time_max' => 15,
            'country' => 'US',
            'limit' => 5,
        ],
    ]]);

    expect($results)->toHaveCount(1)
        ->and($results[0]['tool_name'])->toBe('workspace.search_suppliers');

    $payload = $results[0]['result'];

    expect($payload['items'])->toHaveCount(1);

    $supplierPayload = $payload['items'][0];

    expect($supplierPayload)->toMatchArray([
        'supplier_id' => $matchingSupplier->id,
        'name' => 'Precision Dynamics',
        'status' => 'approved',
        'risk_grade' => RiskGrade::Low->value,
        'lead_time_days' => 12,
    ]);

    expect($supplierPayload['capability_highlights']['methods'][0])->toBe('CNC Milling')
        ->and($supplierPayload['overall_score'])->toBeFloat();

    expect($payload['meta']['status_counts']['approved'])->toBe(1)
        ->and($payload['meta']['filters']['country'])->toBe('US');
});

it('gets supplier profiles via workspace.get_supplier', function (): void {
    $company = Company::factory()->create();

    $supplier = Supplier::factory()->create([
        'company_id' => $company->id,
        'name' => 'Aero Works',
        'status' => 'approved',
        'email' => 'buyer@aeroworks.test',
        'phone' => '+1-555-0101',
        'website' => 'https://aeroworks.test',
        'address' => '1000 Innovation Dr',
        'city' => 'Austin',
        'country' => 'US',
        'lead_time_days' => 18,
        'moq' => 25,
        'rating_avg' => 4.6,
        'risk_grade' => RiskGrade::Medium,
        'capabilities' => [
            'methods' => ['CNC Milling'],
            'materials' => ['Aluminum 6061'],
        ],
    ]);

    SupplierRiskScore::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'on_time_delivery_rate' => 0.95,
        'defect_rate' => 0.02,
        'responsiveness_rate' => 0.9,
        'overall_score' => 0.88,
        'badges_json' => ['preferred', 'qms'],
    ]);

    SupplierDocument::factory()->count(2)->for($supplier, 'supplier')->create([
        'company_id' => $company->id,
        'status' => 'valid',
    ]);

    Quote::factory()->for($company)->create(['supplier_id' => $supplier->id]);
    Quote::factory()->for($company)->create(['supplier_id' => $supplier->id]);

    $openPo = PurchaseOrder::factory()->for($company)->create([
        'supplier_id' => $supplier->id,
        'status' => 'sent',
        'currency' => 'USD',
        'total' => 12500,
        'total_minor' => 1_250_000,
        'ordered_at' => now()->subDay(),
    ]);

    PurchaseOrder::factory()->for($company)->create([
        'supplier_id' => $supplier->id,
        'status' => 'cancelled',
    ]);

    Invoice::factory()->for($company)->create([
        'supplier_id' => $supplier->id,
        'purchase_order_id' => $openPo->id,
        'status' => InvoiceStatus::Approved->value,
        'due_date' => now()->addDays(10),
        'currency' => 'USD',
    ]);

    Invoice::factory()->for($company)->create([
        'supplier_id' => $supplier->id,
        'status' => InvoiceStatus::Paid->value,
        'due_date' => now()->addDays(5),
    ]);

    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.get_supplier',
        'call_id' => 'call-get-supplier',
        'arguments' => ['supplier_id' => $supplier->id],
    ]]);

    expect($results)->toHaveCount(1)
        ->and($results[0]['tool_name'])->toBe('workspace.get_supplier');

    $payload = $results[0]['result']['supplier'];

    expect($payload['supplier_id'])->toBe($supplier->id)
        ->and($payload['contact'])->toMatchArray([
            'email' => 'buyer@aeroworks.test',
            'phone' => '+1-555-0101',
        ])
        ->and($payload['scorecard']['on_time_delivery_pct'])->toEqualWithDelta(95.0, 0.001)
        ->and($payload['documents']['total'])->toBe(2);

    expect($payload['activity']['quotes_total'])->toBe(2)
        ->and($payload['activity']['recent_purchase_orders'])->not()->toBeEmpty()
        ->and($payload['activity']['recent_invoices'])->not()->toBeEmpty();
});

it('summarizes supplier risk via workspace.supplier_risk_snapshot', function (): void {
    $company = Company::factory()->create();

    $supplier = Supplier::factory()->create([
        'company_id' => $company->id,
        'name' => 'Beta Machining',
        'status' => 'approved',
        'risk_grade' => RiskGrade::Medium,
        'rating_avg' => 4.1,
        'city' => 'Dallas',
        'country' => 'US',
    ]);

    SupplierRiskScore::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'on_time_delivery_rate' => 0.92,
        'defect_rate' => 0.04,
        'overall_score' => 0.81,
    ]);

    PurchaseOrder::factory()->for($company)->create([
        'supplier_id' => $supplier->id,
        'status' => 'sent',
    ]);

    PurchaseOrder::factory()->for($company)->create([
        'supplier_id' => $supplier->id,
        'status' => 'cancelled',
    ]);

    Invoice::factory()->for($company)->create([
        'supplier_id' => $supplier->id,
        'status' => InvoiceStatus::Rejected->value,
    ]);

    Invoice::factory()->for($company)->create([
        'supplier_id' => $supplier->id,
        'status' => InvoiceStatus::Approved->value,
    ]);

    Invoice::factory()->for($company)->create([
        'supplier_id' => $supplier->id,
        'status' => InvoiceStatus::Approved->value,
    ]);

    Invoice::factory()->for($company)->create([
        'supplier_id' => $supplier->id,
        'status' => InvoiceStatus::Paid->value,
    ]);

    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.supplier_risk_snapshot',
        'call_id' => 'call-risk',
        'arguments' => ['supplier_id' => $supplier->id],
    ]]);

    expect($results)->toHaveCount(1)
        ->and($results[0]['tool_name'])->toBe('workspace.supplier_risk_snapshot');

    $snapshot = $results[0]['result']['snapshot'];
    $metrics = $snapshot['metrics'];

    expect($snapshot['supplier']['supplier_id'])->toBe($supplier->id)
        ->and($metrics['on_time_delivery_pct'])->toEqualWithDelta(92.0, 0.01)
        ->and($metrics['defect_pct'])->toEqualWithDelta(4.0, 0.01)
        ->and($metrics['dispute_pct'])->toEqualWithDelta(25.0, 0.01)
        ->and($metrics['open_purchase_orders'])->toBe(1)
        ->and($metrics['unpaid_invoices'])->toBe(3)
        ->and($metrics['computed_risk_score'])->toEqualWithDelta(88.1, 0.1);

    expect($snapshot['meta']['samples']['invoice_count'])->toBe(4);
});

it('enforces module permissions via workspace.policy_check', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_requester',
    ]);

    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.policy_check',
        'call_id' => 'policy-1',
        'arguments' => [
            'action_type' => 'purchase_order.approve',
            'user_id' => $user->id,
            'payload' => ['total_value' => 12000],
        ],
    ]]);

    $policy = $results[0]['result'];

    expect($policy['allowed'])->toBeFalse()
        ->and($policy['reasons'])->toContain('orders.write permission is required to run this action.')
        ->and($policy['required_approvals'][0]['value'])->toBe('orders.write');
});

it('requires finance approval for high value workspace.policy_check transactions', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'supplier_admin',
    ]);

    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.policy_check',
        'call_id' => 'policy-2',
        'arguments' => [
            'action_type' => 'purchase_order.release',
            'user_id' => $user->id,
            'payload' => ['total_value' => 125000],
        ],
    ]]);

    $policy = $results[0]['result'];

    expect($policy['allowed'])->toBeFalse()
        ->and($policy['reasons'])->toContain('Finance approval required for totals above $50,000.00.')
        ->and(collect($policy['required_approvals'])->pluck('value'))->toContain('finance.write');
});

it('flags high risk suppliers via workspace.policy_check', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    $supplier = Supplier::factory()->create([
        'company_id' => $company->id,
        'name' => 'Gamma Precision',
        'risk_grade' => RiskGrade::High,
    ]);

    SupplierRiskScore::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'overall_score' => 0.55,
    ]);

    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.policy_check',
        'call_id' => 'policy-3',
        'arguments' => [
            'action_type' => 'supplier_onboard_draft',
            'user_id' => $user->id,
            'payload' => ['supplier_id' => $supplier->id],
        ],
    ]]);

    $policy = $results[0]['result'];

    expect($policy['allowed'])->toBeFalse()
        ->and($policy['reasons'][0])->toContain('high risk')
        ->and(collect($policy['required_approvals'])->pluck('value'))->toContain('quality.high_risk_supplier');
});

it('creates workflow approval requests via workspace.request_approval', function (): void {
    $company = Company::factory()->create();
    $requester = User::factory()->create(['company_id' => $company->id]);

    $workflow = AiWorkflow::factory()->create([
        'company_id' => $company->id,
        'user_id' => $requester->id,
        'workflow_id' => 'wf-request-1',
        'steps_json' => ['steps' => []],
        'current_step' => 1,
    ]);

    $step = AiWorkflowStep::factory()->create([
        'company_id' => $company->id,
        'workflow_id' => $workflow->workflow_id,
        'step_index' => 1,
        'action_type' => 'po_draft',
    ]);

    app(WorkflowService::class)->refreshWorkflowSnapshot($workflow->fresh());

    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.request_approval',
        'call_id' => 'call-approval-request',
        'arguments' => [
            'workflow_id' => $workflow->workflow_id,
            'step_index' => $step->step_index,
            'entity_type' => 'purchase_order',
            'entity_id' => 'PO-500',
            'step_type' => 'po_draft',
            'approver_role' => 'finance',
            'message' => 'Need finance review before release.',
        ],
    ]]);

    expect($results)->toHaveCount(1)
        ->and($results[0]['tool_name'])->toBe('workspace.request_approval');

    $payload = $results[0]['result'];

    expect($payload['request'])->toMatchArray([
        'workflow_step_id' => $step->id,
        'entity_type' => 'purchase_order',
        'approver_role' => 'finance',
        'status' => 'pending',
    ]);

    expect($payload['request']['requested_by']['id'])->toBe($requester->id);

    $record = AiApprovalRequest::query()->first();

    expect($record)->not()->toBeNull()
        ->and($record->workflow_step_id)->toBe($step->id)
        ->and($record->entity_id)->toBe('PO-500');

    $workflow = $workflow->fresh();
    $snapshot = collect($workflow->steps_json['steps'] ?? [])->firstWhere('step_index', $step->step_index);

    expect($snapshot['has_pending_approval_request'] ?? false)->toBeTrue();
});

it('searches dispute tasks with invoice and receipt context via workspace.search_disputes', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    $purchaseOrder = PurchaseOrder::factory()->for($company)->create([
        'po_number' => 'PO-9001',
    ]);

    $invoice = Invoice::factory()->for($company)->create([
        'purchase_order_id' => $purchaseOrder->id,
        'invoice_number' => 'INV-9001',
        'status' => InvoiceStatus::Submitted->value,
    ]);

    $receipt = GoodsReceiptNote::factory()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'number' => 'GRN-9001',
        'status' => 'complete',
    ]);

    $matchingTask = InvoiceDisputeTask::factory()->create([
        'company_id' => $company->id,
        'invoice_id' => $invoice->id,
        'purchase_order_id' => $purchaseOrder->id,
        'goods_receipt_note_id' => $receipt->id,
        'status' => 'open',
        'resolution_type' => 'qty_variance',
        'summary' => 'Qty variance detected on INV-9001',
        'reason_codes' => ['qty_variance', 'late_receipt'],
        'due_at' => now()->addDays(3),
    ]);

    InvoiceDisputeTask::factory()->create([
        'company_id' => $company->id,
        'invoice_id' => Invoice::factory()->for($company)->create([
            'invoice_number' => 'INV-9900',
            'purchase_order_id' => $purchaseOrder->id,
        ])->id,
        'purchase_order_id' => $purchaseOrder->id,
        'status' => 'resolved',
        'summary' => 'Pricing mismatch for INV-9900',
    ]);

    InvoiceDisputeTask::factory()->create([
        'company_id' => $otherCompany->id,
        'invoice_id' => Invoice::factory()->for($otherCompany)->create()->id,
        'purchase_order_id' => PurchaseOrder::factory()->for($otherCompany)->create()->id,
        'status' => 'open',
        'summary' => 'Other tenant dispute',
    ]);

    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.search_disputes',
        'call_id' => 'call-disputes',
        'arguments' => [
            'query' => 'INV-9001',
            'limit' => 10,
        ],
    ]]);

    expect($results)->toHaveCount(1)
        ->and($results[0]['tool_name'])->toBe('workspace.search_disputes');

    $payload = $results[0]['result'];

    expect($payload['items'])->toHaveCount(1)
        ->and($payload['meta']['total_count'])->toBe(1)
        ->and($payload['meta']['status_counts']['open'] ?? 0)->toBe(1);

    $item = $payload['items'][0];

    expect($item)->toMatchArray([
        'dispute_id' => $matchingTask->id,
        'invoice_id' => $invoice->id,
        'purchase_order_id' => $purchaseOrder->id,
        'resolution_type' => 'qty_variance',
        'status' => 'open',
    ]);

    expect($item['invoice']['invoice_number'])->toBe('INV-9001')
        ->and($item['purchase_order']['po_number'])->toBe('PO-9001')
        ->and($item['receipt']['number'])->toBe('GRN-9001')
        ->and($item['reason_codes'])->toContain('qty_variance')
        ->and($item['due_at'])->toBeString();
});

it('retrieves a detailed dispute payload via workspace.get_dispute', function (): void {
    $company = Company::factory()->create();
    $purchaseOrder = PurchaseOrder::factory()->for($company)->create([
        'po_number' => 'PO-7001',
    ]);

    $invoice = Invoice::factory()->for($company)->create([
        'purchase_order_id' => $purchaseOrder->id,
        'invoice_number' => 'INV-7001',
        'status' => InvoiceStatus::BuyerReview->value,
    ]);

    $receipt = GoodsReceiptNote::factory()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'number' => 'GRN-7001',
        'status' => 'pending',
    ]);

    $creator = User::factory()->create(['company_id' => $company->id]);
    $resolverUser = User::factory()->create(['company_id' => $company->id]);

    $task = InvoiceDisputeTask::factory()->create([
        'company_id' => $company->id,
        'invoice_id' => $invoice->id,
        'purchase_order_id' => $purchaseOrder->id,
        'goods_receipt_note_id' => $receipt->id,
        'resolution_type' => 'pricing_exception',
        'status' => 'resolved',
        'summary' => 'Resolved pricing mismatch',
        'owner_role' => 'finance_admin',
        'requires_hold' => true,
        'due_at' => now()->addDays(1),
        'actions' => [[
            'type' => 'issue_debit_memo',
            'description' => 'Issue debit memo for supplier overcharge.',
            'owner_role' => 'finance_admin',
            'due_in_days' => 2,
            'requires_hold' => false,
        ]],
        'impacted_lines' => [[
            'reference' => 'Line 1',
            'issue' => 'Unit price variance',
            'severity' => 'risk',
            'variance' => 12.5,
            'recommended_action' => 'Hold payment until credit issued.',
        ]],
        'next_steps' => ['Collect credit memo', 'Update PO line pricing'],
        'notes' => ['Supplier acknowledged variance'],
        'reason_codes' => ['price_variance'],
        'created_by' => $creator->id,
        'resolved_by' => $resolverUser->id,
        'resolved_at' => now()->addDays(2),
    ]);

    $resolver = app(WorkspaceToolResolver::class);

    $results = $resolver->resolveBatch($company->id, [[
        'tool_name' => 'workspace.get_dispute',
        'call_id' => 'call-get-dispute',
        'arguments' => [
            'dispute_id' => $task->id,
        ],
    ]]);

    $payload = $results[0]['result'];
    $dispute = $payload['dispute'] ?? null;

    expect($dispute)->not()->toBeNull();

    expect($dispute)->toMatchArray([
        'dispute_id' => $task->id,
        'invoice_id' => $invoice->id,
        'purchase_order_id' => $purchaseOrder->id,
        'resolution_type' => 'pricing_exception',
        'status' => 'resolved',
    ]);

    expect($dispute['invoice']['invoice_number'])->toBe('INV-7001')
        ->and($dispute['purchase_order']['po_number'])->toBe('PO-7001')
        ->and($dispute['receipt']['number'])->toBe('GRN-7001')
        ->and($dispute['actions'][0]['type'])->toBe('issue_debit_memo')
        ->and($dispute['impacted_lines'][0]['reference'])->toBe('Line 1')
        ->and($dispute['reason_codes'])->toContain('price_variance');

    expect($dispute['creator']['user_id'])->toBe($creator->id)
        ->and($dispute['resolver']['user_id'])->toBe($resolverUser->id)
        ->and($dispute['next_steps'])->toContain('Collect credit memo')
        ->and($dispute['notes'])->toContain('Supplier acknowledged variance');
});
