<?php

namespace App\Services\Ai;

use App\Enums\AiChatToolCall;
use App\Exceptions\AiChatException;
use App\Exceptions\AiServiceUnavailableException;
use App\Enums\InvoiceStatus;
use App\Models\AiApprovalRequest;
use App\Models\AiWorkflow;
use App\Models\AiWorkflowStep;
use App\Models\Currency;
use App\Models\Document;
use App\Models\GoodsReceiptLine;
use App\Models\GoodsReceiptNote;
use App\Models\Inventory;
use App\Models\InventorySetting;
use App\Models\Invoice;
use App\Models\InvoiceDisputeTask;
use App\Models\InvoicePayment;
use App\Models\InvoiceLine;
use App\Models\Part;
use App\Models\PartPreferredSupplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\RfqItemAward;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\Policies\PolicyCheckService;
use App\Services\Ai\WorkflowService;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class WorkspaceToolResolver
{
    public const RESOLVER_METHODS = [
        AiChatToolCall::SearchRfqs->value => 'handleSearchRfqs',
        AiChatToolCall::SearchPos->value => 'handleSearchPos',
        AiChatToolCall::SearchReceipts->value => 'handleSearchReceipts',
        AiChatToolCall::SearchInvoices->value => 'handleSearchInvoices',
        AiChatToolCall::SearchPayments->value => 'handleSearchPayments',
        AiChatToolCall::SearchDisputes->value => 'handleSearchDisputes',
        AiChatToolCall::SearchContracts->value => 'handleSearchContracts',
        AiChatToolCall::SearchItems->value => 'handleSearchItems',
        AiChatToolCall::SearchSuppliers->value => 'handleSearchSuppliers',
        AiChatToolCall::Navigate->value => 'handleNavigate',
        AiChatToolCall::NextBestAction->value => 'handleNextBestAction',
        AiChatToolCall::GetRfq->value => 'handleGetRfq',
        AiChatToolCall::GetPo->value => 'handleGetPo',
        AiChatToolCall::GetReceipt->value => 'handleGetReceipt',
        AiChatToolCall::GetReceipts->value => 'handleGetReceipts',
        AiChatToolCall::GetInvoice->value => 'handleGetInvoice',
        AiChatToolCall::GetInvoices->value => 'handleGetInvoices',
        AiChatToolCall::GetPayment->value => 'handleGetPayment',
        AiChatToolCall::GetDispute->value => 'handleGetDispute',
        AiChatToolCall::GetContract->value => 'handleGetContract',
        AiChatToolCall::GetItem->value => 'handleGetItem',
        AiChatToolCall::GetSupplier->value => 'handleGetSupplier',
        AiChatToolCall::ListSuppliers->value => 'handleListSuppliers',
        AiChatToolCall::SupplierRiskSnapshot->value => 'handleSupplierRiskSnapshot',
        AiChatToolCall::PolicyCheck->value => 'handlePolicyCheck',
        AiChatToolCall::RequestApproval->value => 'handleRequestApproval',
        AiChatToolCall::GetQuotesForRfq->value => 'handleGetQuotesForRfq',
        AiChatToolCall::GetInventoryItem->value => 'handleGetInventoryItem',
        AiChatToolCall::LowStock->value => 'handleLowStock',
        AiChatToolCall::GetAwards->value => 'handleGetAwards',
        AiChatToolCall::QuoteStats->value => 'handleQuoteStats',
        AiChatToolCall::ProcurementSnapshot->value => 'handleProcurementSnapshot',
        AiChatToolCall::ApproveInvoice->value => 'handleApproveInvoice',
        AiChatToolCall::Help->value => 'handleHelp',
    ];

    public const MAX_TOOL_CALLS = 5;

    public function __construct(
        private readonly AiClient $client,
        private readonly PolicyCheckService $policyCheckService,
        private readonly WorkflowService $workflowService,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function supportedTools(): array
    {
        return array_keys(self::RESOLVER_METHODS);
    }

    private const QUOTE_STATUSES = [
        'draft',
        'submitted',
        'withdrawn',
        'rejected',
        'awarded',
    ];

    private const PURCHASE_ORDER_STATUSES = [
        'draft',
        'sent',
        'acknowledged',
        'confirmed',
        'cancelled',
    ];

    private const RECEIPT_STATUSES = [
        'pending',
        'draft',
        'inspecting',
        'complete',
        'accepted',
        'ncr_raised',
        'rejected',
    ];

    private const INVOICE_STATUSES = [
        InvoiceStatus::Draft->value,
        InvoiceStatus::Submitted->value,
        InvoiceStatus::BuyerReview->value,
        InvoiceStatus::Approved->value,
        InvoiceStatus::Rejected->value,
        InvoiceStatus::Paid->value,
    ];

    private const PAYMENT_STATUSES = [
        'pending',
        'paid',
    ];

    private const ITEM_STATUSES = [
        'active',
        'inactive',
    ];

    private const SUPPLIER_STATUSES = [
        'pending',
        'approved',
        'rejected',
        'suspended',
    ];

    private const OPEN_PURCHASE_ORDER_STATUSES = [
        'draft',
        'sent',
        'acknowledged',
        'confirmed',
        'approved',
    ];

    private const CONTRACT_CATEGORY = 'contract';

    private const NAVIGATION_MODULE_MAP = [
        'rfq' => [
            'label' => 'RFQs',
            'list_url' => '/app/rfqs',
            'routes' => [
                'list' => [
                    'url' => '/app/rfqs',
                    'label' => 'RFQ Workspace',
                ],
                'create' => [
                    'url' => '/app/rfqs/new',
                    'label' => 'Create RFQ',
                    'breadcrumbs_label' => 'Create RFQ',
                ],
                'detail' => [
                    'template' => '/app/rfqs/%s',
                    'label_template' => 'RFQ %s',
                ],
            ],
        ],
        'quote' => [
            'label' => 'Quotes',
            'list_url' => '/app/quotes',
            'routes' => [
                'list' => [
                    'url' => '/app/quotes',
                    'label' => 'Quote Workspace',
                ],
                'detail' => [
                    'template' => '/app/quotes/%s',
                    'label_template' => 'Quote %s',
                ],
            ],
        ],
        'po' => [
            'label' => 'Purchase Orders',
            'list_url' => '/app/purchase-orders',
            'routes' => [
                'list' => [
                    'url' => '/app/purchase-orders',
                    'label' => 'Purchase Orders',
                ],
                'detail' => [
                    'template' => '/app/purchase-orders/%s',
                    'label_template' => 'PO %s',
                ],
            ],
        ],
        'receipt' => [
            'label' => 'Receiving',
            'list_url' => '/app/receiving',
            'routes' => [
                'list' => [
                    'url' => '/app/receiving',
                    'label' => 'Receiving Workspace',
                ],
                'create' => [
                    'url' => '/app/receiving/new',
                    'label' => 'Log Receipt',
                    'breadcrumbs_label' => 'New Receipt',
                ],
                'detail' => [
                    'template' => '/app/receiving/%s',
                    'label_template' => 'Receipt %s',
                ],
            ],
        ],
        'invoice' => [
            'label' => 'Invoices',
            'list_url' => '/app/invoices',
            'routes' => [
                'list' => [
                    'url' => '/app/invoices',
                    'label' => 'Invoices',
                ],
                'detail' => [
                    'template' => '/app/invoices/%s',
                    'label_template' => 'Invoice %s',
                ],
            ],
        ],
        'payment' => [
            'label' => 'Payments',
            'list_url' => '/app/invoices',
            'routes' => [
                'list' => [
                    'url' => '/app/invoices',
                    'label' => 'Invoice & Payment Center',
                ],
                'detail' => [
                    'template' => '/app/invoices/%s',
                    'label_template' => 'Payment detail for %s',
                ],
            ],
        ],
        'supplier' => [
            'label' => 'Suppliers',
            'list_url' => '/app/suppliers',
            'routes' => [
                'list' => [
                    'url' => '/app/suppliers',
                    'label' => 'Supplier Directory',
                ],
                'detail' => [
                    'template' => '/app/suppliers/%s',
                    'label_template' => 'Supplier %s',
                ],
            ],
        ],
        'item' => [
            'label' => 'Items',
            'list_url' => '/app/inventory/items',
            'routes' => [
                'list' => [
                    'url' => '/app/inventory/items',
                    'label' => 'Item Master',
                ],
                'create' => [
                    'url' => '/app/inventory/items/new',
                    'label' => 'Create Item',
                    'breadcrumbs_label' => 'New Item',
                ],
                'detail' => [
                    'template' => '/app/inventory/items/%s',
                    'label_template' => 'Item %s',
                ],
            ],
        ],
    ];

    private const NAVIGATION_MODULE_ALIASES = [
        'rfq' => 'rfq',
        'rfqs' => 'rfq',
        'request_for_quote' => 'rfq',
        'quotes' => 'quote',
        'quote' => 'quote',
        'po' => 'po',
        'purchase_order' => 'po',
        'purchase_orders' => 'po',
        'orders' => 'po',
        'order' => 'po',
        'receiving' => 'receipt',
        'receipt' => 'receipt',
        'receipts' => 'receipt',
        'grn' => 'receipt',
        'invoice' => 'invoice',
        'invoices' => 'invoice',
        'payment' => 'payment',
        'payments' => 'payment',
        'supplier' => 'supplier',
        'suppliers' => 'supplier',
        'vendor' => 'supplier',
        'vendors' => 'supplier',
        'item' => 'item',
        'items' => 'item',
        'inventory' => 'item',
    ];

    private const NAVIGATION_ACTION_ALIASES = [
        'list' => 'list',
        'index' => 'list',
        'browse' => 'list',
        'overview' => 'list',
        'detail' => 'detail',
        'view' => 'detail',
        'show' => 'detail',
        'open' => 'detail',
        'create' => 'create',
        'new' => 'create',
        'draft' => 'create',
    ];

    private const NEXT_BEST_FALLBACKS = [
        'rfqs' => [
            'title' => 'Review sourcing queue',
            'reason_template' => '%d RFQs are active across drafting, publishing, or awarding.',
            'module' => 'rfq',
            'action' => 'list',
            'threshold' => 1,
            'priority' => 50,
        ],
        'quotes' => [
            'title' => 'Follow up on supplier quotes',
            'reason_template' => '%d quotes need review before you can award work.',
            'module' => 'quote',
            'action' => 'list',
            'threshold' => 1,
            'priority' => 60,
        ],
        'purchase_orders' => [
            'title' => 'Monitor purchase orders in flight',
            'reason_template' => '%d purchase orders are currently open.',
            'module' => 'po',
            'action' => 'list',
            'threshold' => 1,
            'priority' => 70,
        ],
        'receipts' => [
            'title' => 'Check receiving queue',
            'reason_template' => '%d receipts are pending inspection or completion.',
            'module' => 'receipt',
            'action' => 'list',
            'threshold' => 1,
            'priority' => 80,
        ],
        'invoices' => [
            'title' => 'Keep invoices moving',
            'reason_template' => '%d invoices are awaiting action before payment.',
            'module' => 'invoice',
            'action' => 'list',
            'threshold' => 1,
            'priority' => 90,
        ],
    ];

    /**
     * @param list<array{tool_name:string,call_id:string,arguments?:array<string, mixed>}> $toolCalls
     * @return list<array{tool_name:string,call_id:string,result:array<string, mixed>|null}>
     *
     * @throws AiChatException
     */
    public function resolveBatch(int $companyId, array $toolCalls): array
    {
        $sanitizedCalls = array_slice($toolCalls, 0, self::MAX_TOOL_CALLS);
        $results = [];

        foreach ($sanitizedCalls as $call) {
            $toolName = (string) ($call['tool_name'] ?? '');
            $callId = (string) ($call['call_id'] ?? Str::uuid()->toString());
            $arguments = isset($call['arguments']) && is_array($call['arguments']) ? $call['arguments'] : [];

            if (! array_key_exists($toolName, self::RESOLVER_METHODS)) {
                throw new AiChatException(sprintf('Unsupported workspace tool "%s" requested.', $toolName));
            }

            $results[] = [
                'tool_name' => $toolName,
                'call_id' => $callId,
                'result' => $this->dispatch($companyId, $toolName, $arguments),
            ];
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>|null
     */
    private function dispatch(int $companyId, string $toolName, array $arguments): ?array
    {
        $method = self::RESOLVER_METHODS[$toolName] ?? null;

        if ($method === null || ! method_exists($this, $method)) {
            return null;
        }

        return $this->{$method}($companyId, $arguments);
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleSearchRfqs(int $companyId, array $arguments): array
    {
        $limit = $this->sanitizeLimit($arguments['limit'] ?? null, 5, 25);
        $keyword = trim((string) ($arguments['query'] ?? ''));
        $statuses = $this->sanitizeStatuses($arguments['statuses'] ?? null);

        $query = RFQ::query()
            ->forCompany($companyId);

        if ($keyword !== '') {
            $query->where(static function (Builder $query) use ($keyword): void {
                $like = '%' . $keyword . '%';
                $query->where('number', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('method', 'like', $like);
            });
        }

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        $totalCount = (clone $query)->count();
        $statusCounts = (clone $query)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn ($count) => (int) $count)
            ->all();

        $rfqs = (clone $query)
            ->orderByDesc('updated_at')
            ->select(['id', 'number', 'title', 'status', 'due_at', 'close_at'])
            ->limit($limit)
            ->get();

        return [
            'items' => $rfqs->map(static function (RFQ $rfq): array {
                return [
                    'rfq_id' => $rfq->id,
                    'number' => $rfq->number,
                    'title' => $rfq->title,
                    'status' => $rfq->status,
                    'due_at' => optional($rfq->due_at)->toIso8601String(),
                    'close_at' => optional($rfq->close_at)->toIso8601String(),
                ];
            })->all(),
            'meta' => [
                'limit' => $limit,
                'query' => $keyword,
                'statuses' => $statuses,
                'total_count' => $totalCount,
                'status_counts' => $statusCounts,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleSearchPos(int $companyId, array $arguments): array
    {
        $limit = $this->sanitizeLimit($arguments['limit'] ?? null, 5, 25);
        $keyword = $this->stringValue($arguments['query'] ?? null) ?? '';
        $statuses = $this->sanitizePurchaseOrderStatuses($arguments['statuses'] ?? null);

        $baseQuery = PurchaseOrder::query()->forCompany($companyId);

        if ($keyword !== '') {
            $baseQuery->where(static function (Builder $query) use ($keyword): void {
                $like = '%' . $keyword . '%';
                $query->where('po_number', 'like', $like)
                    ->orWhere('incoterm', 'like', $like)
                    ->orWhereHas('supplier', static function (Builder $supplierQuery) use ($like): void {
                        $supplierQuery->where('name', 'like', $like);
                    });
            });
        }

        if ($statuses !== []) {
            $baseQuery->whereIn('status', $statuses);
        }

        $totalCount = (clone $baseQuery)->count();

        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn ($count) => (int) $count)
            ->all();

        $orders = (clone $baseQuery)
            ->with([
                'supplier' => static fn ($query) => $query
                    ->withTrashed()
                    ->select(['id', 'name', 'city', 'country']),
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id',
                'supplier_id',
                'po_number',
                'status',
                'currency',
                'subtotal',
                'subtotal_minor',
                'tax_amount',
                'tax_amount_minor',
                'total',
                'total_minor',
                'ordered_at',
                'expected_at',
                'sent_at',
                'acknowledged_at',
            ]);

        $items = $orders->map(function (PurchaseOrder $order): array {
            $currency = strtoupper($order->currency ?? 'USD');

            return [
                'po_id' => $order->id,
                'po_number' => $order->po_number,
                'status' => $order->status,
                'currency' => $currency,
                'subtotal' => $this->formatMoney($order->subtotal, $order->subtotal_minor, $currency),
                'tax_total' => $this->formatMoney($order->tax_amount, $order->tax_amount_minor, $currency),
                'total' => $this->formatMoney($order->total, $order->total_minor, $currency),
                'supplier' => $order->supplier ? [
                    'supplier_id' => $order->supplier->id,
                    'name' => $order->supplier->name,
                    'location' => trim($order->supplier->city . ', ' . $order->supplier->country, ', '),
                ] : null,
                'ordered_at' => $this->isoDateTime($order->ordered_at),
                'expected_at' => $this->isoDateTime($order->expected_at),
                'sent_at' => $this->isoDateTime($order->sent_at),
                'acknowledged_at' => $this->isoDateTime($order->acknowledged_at),
            ];
        })->all();

        return [
            'items' => $items,
            'meta' => [
                'limit' => $limit,
                'query' => $keyword,
                'statuses' => $statuses,
                'total_count' => $totalCount,
                'status_counts' => $statusCounts,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleQuoteStats(int $companyId, array $arguments): array
    {
        $limit = $this->sanitizeLimit($arguments['limit'] ?? null, 5, 25);
        $statuses = $this->sanitizeQuoteStatuses($arguments['statuses'] ?? null);

        $query = Quote::query()->forCompany($companyId);

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        $totalCount = (clone $query)->count();
        $statusCounts = (clone $query)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn ($count) => (int) $count)
            ->all();

        $quotes = (clone $query)
            ->with(['supplier' => static fn ($builder) => $builder->select(['id', 'name'])->withTrashed()])
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'rfq_id', 'supplier_id', 'status', 'total_price', 'currency', 'submitted_at', 'created_at']);

        return [
            'items' => $quotes->map(static function (Quote $quote): array {
                return [
                    'quote_id' => $quote->id,
                    'rfq_id' => $quote->rfq_id,
                    'supplier' => $quote->supplier ? [
                        'supplier_id' => $quote->supplier->id,
                        'name' => $quote->supplier->name,
                    ] : null,
                    'status' => $quote->status,
                    'total_price' => $quote->total_price !== null ? (float) $quote->total_price : null,
                    'currency' => $quote->currency,
                    'submitted_at' => optional($quote->submitted_at ?? $quote->created_at)->toIso8601String(),
                ];
            })->all(),
            'meta' => [
                'limit' => $limit,
                'statuses' => $statuses,
                'total_count' => $totalCount,
                'status_counts' => $statusCounts,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleProcurementSnapshot(int $companyId, array $arguments): array
    {
        $limit = $this->sanitizeLimit($arguments['limit'] ?? null, 5, 10);

        $rfqQuery = RFQ::query()->forCompany($companyId);
        $quoteQuery = Quote::query()->forCompany($companyId);
        $poQuery = PurchaseOrder::query()->forCompany($companyId);
        $receiptQuery = GoodsReceiptNote::query()->forCompany($companyId);
        $invoiceQuery = Invoice::query()->forCompany($companyId);

        $rfqs = [
            'total_count' => (clone $rfqQuery)->count(),
            'status_counts' => $this->summarizeStatusCounts($rfqQuery, RFQ::STATUSES),
            'latest' => (clone $rfqQuery)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get(['id', 'number', 'title', 'status', 'due_at', 'updated_at'])
                ->map(static function (RFQ $rfq): array {
                    return [
                        'rfq_id' => $rfq->id,
                        'number' => $rfq->number,
                        'title' => $rfq->title,
                        'status' => $rfq->status,
                        'due_at' => optional($rfq->due_at)->toIso8601String(),
                        'updated_at' => optional($rfq->updated_at)->toIso8601String(),
                    ];
                })
                ->all(),
        ];

        $quotes = [
            'total_count' => (clone $quoteQuery)->count(),
            'status_counts' => $this->summarizeStatusCounts($quoteQuery, self::QUOTE_STATUSES),
            'latest' => (clone $quoteQuery)
                ->with([
                    'supplier' => static fn ($builder) => $builder
                        ->withTrashed()
                        ->select(['id', 'name']),
                ])
                ->orderByDesc('submitted_at')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get([
                    'id',
                    'rfq_id',
                    'supplier_id',
                    'status',
                    'currency',
                    'total_price',
                    'submitted_at',
                    'updated_at',
                    'created_at',
                ])
                ->map(static function (Quote $quote): array {
                    $submittedAt = $quote->submitted_at ?? $quote->updated_at ?? $quote->created_at;

                    return [
                        'quote_id' => $quote->id,
                        'rfq_id' => $quote->rfq_id,
                        'status' => $quote->status,
                        'submitted_at' => optional($submittedAt)->toIso8601String(),
                        'currency' => $quote->currency,
                        'total_price' => $quote->total_price !== null ? (float) $quote->total_price : null,
                        'supplier' => $quote->supplier ? [
                            'supplier_id' => $quote->supplier->id,
                            'name' => $quote->supplier->name,
                        ] : null,
                    ];
                })
                ->all(),
        ];

        $purchaseOrders = [
            'total_count' => (clone $poQuery)->count(),
            'status_counts' => $this->summarizeStatusCounts($poQuery, self::PURCHASE_ORDER_STATUSES),
            'latest' => (clone $poQuery)
                ->with([
                    'supplier' => static fn ($builder) => $builder
                        ->withTrashed()
                        ->select(['id', 'name', 'city', 'country']),
                ])
                ->orderByDesc('ordered_at')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get([
                    'id',
                    'supplier_id',
                    'po_number',
                    'status',
                    'currency',
                    'total',
                    'total_minor',
                    'ordered_at',
                    'expected_at',
                    'updated_at',
                ])
                ->map(function (PurchaseOrder $order): array {
                    $currency = strtoupper($order->currency ?? 'USD');

                    return [
                        'po_id' => $order->id,
                        'po_number' => $order->po_number,
                        'status' => $order->status,
                        'ordered_at' => $this->isoDateTime($order->ordered_at ?? $order->updated_at),
                        'expected_at' => $this->isoDateTime($order->expected_at),
                        'currency' => $currency,
                        'total' => $this->formatMoney($order->total, $order->total_minor, $currency),
                        'supplier' => $order->supplier ? [
                            'supplier_id' => $order->supplier->id,
                            'name' => $order->supplier->name,
                            'location' => $this->formatLocation($order->supplier->city ?? null, $order->supplier->country ?? null),
                        ] : null,
                    ];
                })
                ->all(),
        ];

        $receipts = [
            'total_count' => (clone $receiptQuery)->count(),
            'status_counts' => $this->summarizeStatusCounts($receiptQuery, self::RECEIPT_STATUSES),
            'latest' => (clone $receiptQuery)
                ->with([
                    'purchaseOrder' => static fn ($builder) => $builder
                        ->select(['id', 'company_id', 'po_number', 'supplier_id'])
                        ->with([
                            'supplier' => static fn ($supplierQuery) => $supplierQuery
                                ->withTrashed()
                                ->select(['id', 'name']),
                        ]),
                ])
                ->orderByDesc('inspected_at')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get([
                    'id',
                    'purchase_order_id',
                    'number',
                    'status',
                    'inspected_at',
                    'created_at',
                    'updated_at',
                ])
                ->map(function (GoodsReceiptNote $receipt): array {
                    $purchaseOrder = $receipt->purchaseOrder;
                    $supplier = $purchaseOrder?->supplier;

                    return [
                        'receipt_id' => $receipt->id,
                        'receipt_number' => $receipt->number,
                        'status' => $receipt->status,
                        'received_at' => $this->isoDateTime($receipt->inspected_at ?? $receipt->created_at),
                        'po' => $purchaseOrder ? [
                            'purchase_order_id' => $purchaseOrder->id,
                            'po_number' => $purchaseOrder->po_number,
                        ] : null,
                        'supplier' => $supplier ? [
                            'supplier_id' => $supplier->id,
                            'name' => $supplier->name,
                        ] : null,
                    ];
                })
                ->all(),
        ];

        $invoices = [
            'total_count' => (clone $invoiceQuery)->count(),
            'status_counts' => $this->summarizeStatusCounts($invoiceQuery, self::INVOICE_STATUSES),
            'latest' => (clone $invoiceQuery)
                ->with([
                    'supplier' => static fn ($builder) => $builder
                        ->withTrashed()
                        ->select(['id', 'name']),
                    'purchaseOrder' => static fn ($builder) => $builder
                        ->select(['id', 'company_id', 'po_number']),
                ])
                ->orderByDesc('due_date')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get([
                    'id',
                    'purchase_order_id',
                    'supplier_id',
                    'invoice_number',
                    'status',
                    'currency',
                    'total',
                    'total_minor',
                    'due_date',
                ])
                ->map(function (Invoice $invoice): array {
                    $currency = strtoupper($invoice->currency ?? 'USD');

                    return [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'status' => $invoice->status,
                        'due_date' => $this->isoDateTime($invoice->due_date),
                        'currency' => $currency,
                        'total' => $this->formatMoney($invoice->total, $invoice->total_minor, $currency),
                        'supplier' => $invoice->supplier ? [
                            'supplier_id' => $invoice->supplier->id,
                            'name' => $invoice->supplier->name,
                        ] : null,
                        'purchase_order' => $invoice->purchaseOrder ? [
                            'purchase_order_id' => $invoice->purchaseOrder->id,
                            'po_number' => $invoice->purchaseOrder->po_number,
                        ] : null,
                    ];
                })
                ->all(),
        ];

        return [
            'rfqs' => $rfqs,
            'quotes' => $quotes,
            'purchase_orders' => $purchaseOrders,
            'receipts' => $receipts,
            'invoices' => $invoices,
            'meta' => [
                'limit' => $limit,
                'generated_at' => Carbon::now()->toIso8601String(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleGetPo(int $companyId, array $arguments): array
    {
        $poId = $this->coerceId($arguments['po_id'] ?? null);
        $poNumber = $this->stringValue($arguments['po_number'] ?? null);

        if ($poId === null && $poNumber === null) {
            throw new AiChatException('po_id or po_number is required for workspace.get_po tool.');
        }

        $query = PurchaseOrder::query()
            ->forCompany($companyId)
            ->with([
                'supplier' => static fn ($builder) => $builder
                    ->withTrashed()
                    ->select(['id', 'name', 'city', 'country']),
                'lines' => static fn ($builder) => $builder
                    ->select([
                        'id',
                        'purchase_order_id',
                        'line_no',
                        'description',
                        'quantity',
                        'uom',
                        'unit_price',
                        'unit_price_minor',
                        'delivery_date',
                        'received_qty',
                        'receiving_status',
                    ])
                    ->orderBy('line_no')
                    ->limit(10),
            ])
            ->withCount(['lines as lines_total']);

        if ($poId !== null) {
            $query->whereKey($poId);
        } else {
            $query->where('po_number', $poNumber);
        }

        $purchaseOrder = $query->first([
            'id',
            'supplier_id',
            'po_number',
            'status',
            'currency',
            'incoterm',
            'subtotal',
            'subtotal_minor',
            'tax_amount',
            'tax_amount_minor',
            'total',
            'total_minor',
            'ordered_at',
            'expected_at',
            'sent_at',
            'acknowledged_at',
        ]);

        if (! $purchaseOrder instanceof PurchaseOrder) {
            return [
                'po' => null,
                'meta' => [
                    'po_id' => $poId,
                    'po_number' => $poNumber,
                    'note' => 'Purchase order not found for this workspace.',
                ],
            ];
        }

        $currency = strtoupper($purchaseOrder->currency ?? 'USD');
        $lineItems = $purchaseOrder->lines->map(function (PurchaseOrderLine $line) use ($currency): array {
            $unitPriceMinor = $line->unit_price_minor;

            if ($unitPriceMinor === null) {
                $unitPriceMinor = $this->decimalToMinor($line->unit_price, $currency);
            }

            $quantity = (int) $line->quantity;
            $lineTotalMinor = $unitPriceMinor !== null ? $unitPriceMinor * $quantity : null;

            return [
                'line_id' => $line->id,
                'line_no' => $line->line_no,
                'description' => $line->description,
                'quantity' => $quantity,
                'uom' => $line->uom,
                'receiving_status' => $line->receiving_status,
                'delivery_date' => optional($line->delivery_date)?->toDateString(),
                'received_qty' => $line->received_qty,
                'unit_price' => $this->formatMoney($line->unit_price, $unitPriceMinor, $currency),
                'unit_price_minor' => $unitPriceMinor,
                'line_total' => $lineTotalMinor !== null ? $this->formatMoney(null, $lineTotalMinor, $currency) : null,
                'line_total_minor' => $lineTotalMinor,
            ];
        })->values()->all();

        return [
            'po' => [
                'po_id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'status' => $purchaseOrder->status,
                'currency' => $currency,
                'incoterm' => $purchaseOrder->incoterm,
                'ordered_at' => $this->isoDateTime($purchaseOrder->ordered_at),
                'expected_at' => $this->isoDateTime($purchaseOrder->expected_at),
                'sent_at' => $this->isoDateTime($purchaseOrder->sent_at),
                'acknowledged_at' => $this->isoDateTime($purchaseOrder->acknowledged_at),
                'supplier' => $purchaseOrder->supplier ? [
                    'supplier_id' => $purchaseOrder->supplier->id,
                    'name' => $purchaseOrder->supplier->name,
                    'location' => trim($purchaseOrder->supplier->city . ', ' . $purchaseOrder->supplier->country, ', '),
                ] : null,
                'totals' => [
                    'subtotal' => $this->formatMoney($purchaseOrder->subtotal, $purchaseOrder->subtotal_minor, $currency),
                    'subtotal_minor' => $purchaseOrder->subtotal_minor,
                    'tax' => $this->formatMoney($purchaseOrder->tax_amount, $purchaseOrder->tax_amount_minor, $currency),
                    'tax_minor' => $purchaseOrder->tax_amount_minor,
                    'grand_total' => $this->formatMoney($purchaseOrder->total, $purchaseOrder->total_minor, $currency),
                    'grand_total_minor' => $purchaseOrder->total_minor,
                ],
                'lines' => $lineItems,
                'line_sample_limit' => 10,
                'line_total_count' => (int) ($purchaseOrder->lines_total ?? count($lineItems)),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleGetRfq(int $companyId, array $arguments): array
    {
        $rfqId = $this->coerceId($arguments['rfq_id'] ?? null);

        if ($rfqId === null) {
            throw new AiChatException('rfq_id is required for workspace.get_rfq tool.');
        }

        $rfq = RFQ::query()
            ->forCompany($companyId)
            ->with([
                'items' => static fn ($query) => $query->select(['id', 'rfq_id', 'part_number', 'description', 'quantity', 'uom'])->orderBy('id')->limit(10),
                'awards' => static fn ($query) => $query->select(['id', 'rfq_id', 'supplier_id', 'awarded_qty', 'status'])->orderByDesc('awarded_at')->limit(5),
            ])
            ->withCount(['quotes as quotes_count', 'items as items_count'])
            ->find($rfqId);

        if (! $rfq instanceof RFQ) {
            return ['rfq' => null];
        }

        return [
            'rfq' => [
                'rfq_id' => $rfq->id,
                'number' => $rfq->number,
                'title' => $rfq->title,
                'status' => $rfq->status,
                'due_at' => optional($rfq->due_at)->toIso8601String(),
                'quotes_count' => (int) ($rfq->quotes_count ?? 0),
                'items_count' => (int) ($rfq->items_count ?? 0),
                'items' => $rfq->items->map(static function ($item): array {
                    return [
                        'item_id' => $item->id,
                        'part_number' => $item->part_number,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'uom' => $item->uom,
                    ];
                })->all(),
                'awards' => $rfq->awards->map(static function ($award): array {
                    return [
                        'award_id' => $award->id,
                        'supplier_id' => $award->supplier_id,
                        'awarded_qty' => $award->awarded_qty,
                        'status' => $award->status?->value ?? $award->status,
                    ];
                })->all(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleSearchSuppliers(int $companyId, array $arguments): array
    {
        $limit = $this->sanitizeLimit($arguments['limit'] ?? null, 5, 25);
        $keyword = $this->stringValue($arguments['query'] ?? $arguments['search'] ?? null) ?? '';
        $statuses = $this->sanitizeStatusFilter($arguments['statuses'] ?? null, self::SUPPLIER_STATUSES);
        $country = $this->stringValue($arguments['country'] ?? null);
        $city = $this->stringValue($arguments['city'] ?? null);
        $location = $this->stringValue($arguments['location'] ?? null);
        $ratingMin = isset($arguments['rating_min']) && is_numeric($arguments['rating_min']) ? (float) $arguments['rating_min'] : null;
        $leadTimeMax = isset($arguments['lead_time_max']) && is_numeric($arguments['lead_time_max']) ? (int) $arguments['lead_time_max'] : null;

        $capabilityFilters = [
            'methods' => $this->sanitizeStringArray($arguments['methods'] ?? ($arguments['capabilities'] ?? null)),
            'materials' => $this->sanitizeStringArray($arguments['materials'] ?? null),
            'finishes' => $this->sanitizeStringArray($arguments['finishes'] ?? null),
            'tolerances' => $this->sanitizeStringArray($arguments['tolerances'] ?? null),
            'industries' => $this->sanitizeStringArray($arguments['industries'] ?? null),
        ];

        $certifications = $this->sanitizeStringArray($arguments['certifications'] ?? $arguments['certs'] ?? null);

        $query = Supplier::query()
            ->forCompany($companyId)
            ->with([
                'riskScore' => static fn ($builder) => $builder->select([
                    'id',
                    'company_id',
                    'supplier_id',
                    'overall_score',
                    'on_time_delivery_rate',
                    'defect_rate',
                ]),
            ]);

        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $query->where(static function (Builder $builder) use ($like): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('country', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        if ($country !== null) {
            $query->where('country', $country);
        }

        if ($city !== null) {
            $query->where('city', $city);
        }

        if ($location !== null) {
            $like = '%' . $location . '%';
            $query->where(static function (Builder $builder) use ($like): void {
                $builder->where('city', 'like', $like)
                    ->orWhere('country', 'like', $like);
            });
        }

        if ($ratingMin !== null) {
            $query->where('rating_avg', '>=', $ratingMin);
        }

        if ($leadTimeMax !== null) {
            $query->whereNotNull('lead_time_days')
                ->where('lead_time_days', '<=', $leadTimeMax);
        }

        foreach ($capabilityFilters as $key => $values) {
            if ($values === []) {
                continue;
            }

            $query->where(static function (Builder $builder) use ($key, $values): void {
                foreach ($values as $entry) {
                    $builder->whereJsonContains("capabilities->$key", $entry);
                }
            });
        }

        if ($certifications !== []) {
            $query->whereHas('documents', static function (Builder $builder) use ($certifications, $companyId): void {
                $builder->forCompany($companyId)
                    ->whereIn('type', $certifications);
            });
        }

        $totalCount = (clone $query)->count();
        $statusCounts = $this->summarizeStatusCounts($query, self::SUPPLIER_STATUSES);

        $suppliers = (clone $query)
            ->orderByDesc('verified_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id',
                'company_id',
                'name',
                'status',
                'email',
                'phone',
                'city',
                'country',
                'lead_time_days',
                'rating_avg',
                'capabilities',
                'risk_grade',
                'verified_at',
            ]);

        $items = $suppliers->map(function (Supplier $supplier): array {
            $riskScore = $supplier->riskScore;

            return [
                'supplier_id' => $supplier->id,
                'name' => $supplier->name,
                'status' => $supplier->status,
                'location' => $this->formatLocation($supplier->city ?? null, $supplier->country ?? null),
                'lead_time_days' => $supplier->lead_time_days,
                'rating' => $supplier->rating_avg !== null ? (float) $supplier->rating_avg : null,
                'risk_grade' => $supplier->risk_grade?->value ?? $supplier->risk_grade,
                'verified_at' => $this->isoDateTime($supplier->verified_at),
                'capability_highlights' => $this->capabilityHighlights(is_array($supplier->capabilities) ? $supplier->capabilities : null),
                'overall_score' => $riskScore?->overall_score !== null
                    ? round((float) $riskScore->overall_score * 100, 2)
                    : null,
            ];
        })->all();

        $filtersMeta = [
            'methods' => $capabilityFilters['methods'],
            'materials' => $capabilityFilters['materials'],
            'finishes' => $capabilityFilters['finishes'],
            'tolerances' => $capabilityFilters['tolerances'],
            'industries' => $capabilityFilters['industries'],
            'certifications' => $certifications,
            'rating_min' => $ratingMin,
            'lead_time_max' => $leadTimeMax,
            'country' => $country,
            'city' => $city,
            'location' => $location,
        ];

        return [
            'items' => $items,
            'meta' => [
                'limit' => $limit,
                'query' => $keyword,
                'statuses' => $statuses,
                'total_count' => $totalCount,
                'status_counts' => $statusCounts,
                'filters' => $filtersMeta,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleGetSupplier(int $companyId, array $arguments): array
    {
        $supplierId = $this->coerceId($arguments['supplier_id'] ?? null);
        $supplierName = $this->stringValue($arguments['name'] ?? null);

        if ($supplierId === null && $supplierName === null) {
            throw new AiChatException('supplier_id or name is required for workspace.get_supplier tool.');
        }

        $query = Supplier::query()
            ->forCompany($companyId)
            ->with([
                'riskScore' => static fn ($builder) => $builder->select([
                    'id',
                    'company_id',
                    'supplier_id',
                    'on_time_delivery_rate',
                    'defect_rate',
                    'responsiveness_rate',
                    'overall_score',
                    'badges_json',
                ]),
                'documents' => static fn ($builder) => $builder
                    ->select([
                        'id',
                        'company_id',
                        'supplier_id',
                        'type',
                        'status',
                        'issued_at',
                        'expires_at',
                    ])
                    ->latest('expires_at')
                    ->latest('id')
                    ->limit(5),
            ])
            ->withCount(['documents as documents_total']);

        if ($supplierId !== null) {
            $query->whereKey($supplierId);
        } else {
            $like = '%' . $supplierName . '%';
            $query->where('name', 'like', $like);
        }

        $supplier = $query->first([
            'id',
            'company_id',
            'name',
            'status',
            'email',
            'phone',
            'website',
            'address',
            'city',
            'country',
            'capabilities',
            'lead_time_days',
            'moq',
            'rating_avg',
            'risk_grade',
            'verified_at',
        ]);

        if (! $supplier instanceof Supplier) {
            return [
                'supplier' => null,
                'meta' => [
                    'supplier_id' => $supplierId,
                    'name' => $supplierName,
                    'note' => 'Supplier not found for this workspace.',
                ],
            ];
        }

        $riskScore = $supplier->riskScore;
        $documents = $supplier->documents;

        $quotesCount = Quote::query()
            ->forCompany($companyId)
            ->where('supplier_id', $supplier->id)
            ->count();

        $openPurchaseOrders = $this->countOpenPurchaseOrders($companyId, $supplier->id);

        $openInvoices = Invoice::query()
            ->forCompany($companyId)
            ->where('supplier_id', $supplier->id)
            ->where('status', '!=', InvoiceStatus::Paid->value)
            ->count();

        $recentPurchaseOrders = PurchaseOrder::query()
            ->forCompany($companyId)
            ->where('supplier_id', $supplier->id)
            ->orderByDesc('ordered_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get([
                'id',
                'po_number',
                'status',
                'currency',
                'total',
                'total_minor',
                'ordered_at',
            ])
            ->map(function (PurchaseOrder $order): array {
                $currency = strtoupper($order->currency ?? 'USD');

                return [
                    'po_id' => $order->id,
                    'po_number' => $order->po_number,
                    'status' => $order->status,
                    'ordered_at' => $this->isoDateTime($order->ordered_at),
                    'currency' => $currency,
                    'total' => $this->formatMoney($order->total, $order->total_minor, $currency),
                ];
            })->all();

        $recentInvoices = Invoice::query()
            ->forCompany($companyId)
            ->where('supplier_id', $supplier->id)
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->limit(3)
            ->get([
                'id',
                'invoice_number',
                'status',
                'currency',
                'total',
                'total_minor',
                'due_date',
            ])
            ->map(function (Invoice $invoice): array {
                $currency = strtoupper($invoice->currency ?? 'USD');

                return [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $invoice->status,
                    'due_date' => $this->isoDateTime($invoice->due_date),
                    'currency' => $currency,
                    'total' => $this->formatMoney($invoice->total, $invoice->total_minor, $currency),
                ];
            })->all();

        $documentsSummary = [
            'total' => (int) ($supplier->documents_total ?? $documents->count()),
            'certifications' => $documents->map(function (SupplierDocument $document): array {
                return [
                    'document_id' => $document->id,
                    'type' => $document->type,
                    'status' => $document->status,
                    'issued_at' => $this->isoDateTime($document->issued_at),
                    'expires_at' => $this->isoDateTime($document->expires_at),
                ];
            })->all(),
        ];

        return [
            'supplier' => [
                'supplier_id' => $supplier->id,
                'name' => $supplier->name,
                'status' => $supplier->status,
                'risk_grade' => $supplier->risk_grade?->value ?? $supplier->risk_grade,
                'location' => $this->formatLocation($supplier->city ?? null, $supplier->country ?? null),
                'verified_at' => $this->isoDateTime($supplier->verified_at),
                'contact' => [
                    'email' => $supplier->email,
                    'phone' => $supplier->phone,
                    'website' => $supplier->website,
                    'address' => $supplier->address,
                ],
                'capabilities' => is_array($supplier->capabilities) ? $supplier->capabilities : null,
                'lead_time_days' => $supplier->lead_time_days,
                'moq' => $supplier->moq,
                'rating' => $supplier->rating_avg !== null ? (float) $supplier->rating_avg : null,
                'scorecard' => [
                    'on_time_delivery_pct' => $this->normalizeRateToPercentage($riskScore?->on_time_delivery_rate),
                    'defect_pct' => $this->normalizeRateToPercentage($riskScore?->defect_rate),
                    'responsiveness_pct' => $this->normalizeRateToPercentage($riskScore?->responsiveness_rate),
                    'overall_score' => $riskScore?->overall_score !== null
                        ? round((float) $riskScore->overall_score * 100, 2)
                        : null,
                    'badges' => is_array($riskScore?->badges_json) ? $riskScore->badges_json : [],
                ],
                'documents' => $documentsSummary,
                'activity' => [
                    'quotes_total' => $quotesCount,
                    'open_purchase_orders' => $openPurchaseOrders,
                    'open_invoices' => $openInvoices,
                    'recent_purchase_orders' => $recentPurchaseOrders,
                    'recent_invoices' => $recentInvoices,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleListSuppliers(int $companyId, array $arguments): array
    {
        $limit = $this->sanitizeLimit($arguments['limit'] ?? null, 5, 25);
        $filters = isset($arguments['filters']) && is_array($arguments['filters']) ? $arguments['filters'] : [];

        $builder = Supplier::query()
            ->forCompany($companyId)
            ->select(['id', 'name', 'city', 'country', 'lead_time_days', 'rating_avg', 'risk_grade'])
            ->orderByDesc('updated_at');

        if (isset($filters['country']) && is_string($filters['country'])) {
            $builder->where('country', $filters['country']);
        }

        if (isset($filters['search']) && is_string($filters['search'])) {
            $keyword = '%' . trim($filters['search']) . '%';
            $builder->where(static function (Builder $query) use ($keyword): void {
                $query->where('name', 'like', $keyword)
                    ->orWhere('city', 'like', $keyword);
            });
        }

        $suppliers = $builder->limit($limit)->get();

        return [
            'items' => $suppliers->map(static function (Supplier $supplier): array {
                return [
                    'supplier_id' => $supplier->id,
                    'name' => $supplier->name,
                    'location' => trim($supplier->city . ', ' . $supplier->country, ', '),
                    'lead_time_days' => $supplier->lead_time_days,
                    'rating' => $supplier->rating_avg !== null ? (float) $supplier->rating_avg : null,
                    'risk_grade' => $supplier->risk_grade?->value ?? $supplier->risk_grade,
                ];
            })->all(),
            'meta' => [
                'limit' => $limit,
                'filters' => $filters,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleSupplierRiskSnapshot(int $companyId, array $arguments): array
    {
        $supplierId = $this->coerceId($arguments['supplier_id'] ?? null);

        if ($supplierId === null) {
            throw new AiChatException('supplier_id is required for workspace.supplier_risk_snapshot tool.');
        }

        $supplier = Supplier::query()
            ->forCompany($companyId)
            ->with([
                'riskScore' => static fn ($builder) => $builder->select([
                    'id',
                    'company_id',
                    'supplier_id',
                    'on_time_delivery_rate',
                    'defect_rate',
                    'overall_score',
                    'risk_grade',
                ]),
            ])
            ->find($supplierId, [
                'id',
                'company_id',
                'name',
                'status',
                'city',
                'country',
                'rating_avg',
                'risk_grade',
            ]);

        if (! $supplier instanceof Supplier) {
            return [
                'snapshot' => null,
                'meta' => [
                    'supplier_id' => $supplierId,
                    'note' => 'Supplier not found for this workspace.',
                ],
            ];
        }

        $riskScore = $supplier->riskScore;
        $onTimeFromScore = $this->normalizeRateToPercentage($riskScore?->on_time_delivery_rate);
        $defectFromScore = $this->normalizeRateToPercentage($riskScore?->defect_rate);

        $receiptOnTime = $this->calculateReceiptOnTimeRate($companyId, $supplier->id);
        $receiptDefect = $this->calculateReceiptDefectRate($companyId, $supplier->id);
        $invoiceDispute = $this->calculateDisputeRate($companyId, $supplier->id);

        $onTimePct = $onTimeFromScore ?? $receiptOnTime['percentage'];
        $defectPct = $defectFromScore ?? $receiptDefect['percentage'];
        $disputePct = $invoiceDispute['percentage'];

        $openPurchaseOrders = $this->countOpenPurchaseOrders($companyId, $supplier->id);
        $unpaidInvoices = Invoice::query()
            ->forCompany($companyId)
            ->where('supplier_id', $supplier->id)
            ->where('status', '!=', InvoiceStatus::Paid->value)
            ->count();

        $computedRiskScore = $this->computeSupplierRiskScore($onTimePct, $defectPct, $disputePct);
        $recordedScore = $riskScore?->overall_score !== null
            ? round((float) $riskScore->overall_score * 100, 2)
            : null;

        return [
            'snapshot' => [
                'supplier' => [
                    'supplier_id' => $supplier->id,
                    'name' => $supplier->name,
                    'status' => $supplier->status,
                    'location' => $this->formatLocation($supplier->city ?? null, $supplier->country ?? null),
                    'risk_grade' => $supplier->risk_grade?->value ?? $supplier->risk_grade,
                    'rating' => $supplier->rating_avg !== null ? (float) $supplier->rating_avg : null,
                ],
                'metrics' => [
                    'on_time_delivery_pct' => $onTimePct,
                    'defect_pct' => $defectPct,
                    'dispute_pct' => $disputePct,
                    'open_purchase_orders' => $openPurchaseOrders,
                    'unpaid_invoices' => $unpaidInvoices,
                    'computed_risk_score' => $computedRiskScore,
                    'recorded_risk_score' => $recordedScore,
                ],
                'meta' => [
                    'sources' => [
                        'on_time' => $onTimeFromScore !== null ? 'supplier_risk_scores' : 'receipts',
                        'defect' => $defectFromScore !== null ? 'supplier_risk_scores' : 'receipts',
                        'dispute' => 'invoices',
                    ],
                    'samples' => [
                        'receipts' => $receiptOnTime['sample'],
                        'receipt_lines' => $receiptDefect['sample'],
                        'invoice_count' => $invoiceDispute['sample'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handlePolicyCheck(int $companyId, array $arguments): array
    {
        $actionType = $this->stringValue($arguments['action_type'] ?? null);

        if ($actionType === null) {
            throw new AiChatException('action_type is required for workspace.policy_check tool.');
        }

        $userId = $this->coerceId($arguments['user_id'] ?? null);

        if ($userId === null) {
            throw new AiChatException('user_id is required for workspace.policy_check tool.');
        }

        $payload = isset($arguments['payload']) && is_array($arguments['payload'])
            ? $arguments['payload']
            : [];

        $user = User::query()
            ->where('company_id', $companyId)
            ->whereKey($userId)
            ->first(['id', 'company_id', 'role']);

        if (! $user instanceof User) {
            throw new AiChatException('User context not found for workspace.policy_check tool.');
        }

        $decision = $this->policyCheckService->evaluate($companyId, $user, $actionType, $payload);
        $result = $decision->toArray();
        $result['meta'] = [
            'action_type' => $decision->actionType(),
            'category' => $decision->category(),
            'user_id' => $user->id,
            'evaluated_at' => Carbon::now()->toIso8601String(),
        ];

        return $result;
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleRequestApproval(int $companyId, array $arguments): array
    {
        $workflowId = $this->stringValue($arguments['workflow_id'] ?? null);

        if ($workflowId === null) {
            throw new AiChatException('workflow_id is required for workspace.request_approval tool.');
        }

        $entityType = $this->stringValue($arguments['entity_type'] ?? null);

        if ($entityType === null) {
            throw new AiChatException('entity_type is required for workspace.request_approval tool.');
        }

        $entityId = $this->stringValue($arguments['entity_id'] ?? null);
        $stepType = $this->stringValue($arguments['step_type'] ?? null);
        $approverRole = $this->stringValue($arguments['approver_role'] ?? null);
        $message = $this->stringValue($arguments['message'] ?? null);
        $stepIndex = $this->coerceStepIndex($arguments['step_index'] ?? null);

        $workflow = AiWorkflow::query()
            ->forCompany($companyId)
            ->where('workflow_id', $workflowId)
            ->first(['id', 'company_id', 'workflow_id', 'user_id']);

        if (! $workflow instanceof AiWorkflow) {
            throw new AiChatException('Workflow not found for workspace.request_approval tool.');
        }

        $workflowStep = $this->locateWorkflowStep($companyId, $workflowId, $stepIndex, $stepType);

        if ($workflowStep instanceof AiWorkflowStep) {
            $stepIndex = $workflowStep->step_index;
            $stepType = $workflowStep->action_type ?? $stepType;
        }

        $approverUserId = $this->coerceId($arguments['approver_user_id'] ?? null);

        if ($approverUserId !== null) {
            $approverExists = User::query()
                ->where('company_id', $companyId)
                ->whereKey($approverUserId)
                ->exists();

            if (! $approverExists) {
                throw new AiChatException('Approver user not found for workspace.request_approval tool.');
            }
        }

        $requestedById = $this->coerceId($arguments['requested_by'] ?? $arguments['user_id'] ?? null)
            ?? ($workflow->user_id ?? null);

        if ($requestedById !== null) {
            $requesterExists = User::query()
                ->where('company_id', $companyId)
                ->whereKey($requestedById)
                ->exists();

            if (! $requesterExists) {
                $requestedById = null;
            }
        }

        $existing = AiApprovalRequest::query()
            ->forCompany($companyId)
            ->where('workflow_id', $workflowId)
            ->where('entity_type', $entityType)
            ->where('status', AiApprovalRequest::STATUS_PENDING)
            ->when($entityId !== null, static fn ($query) => $query->where('entity_id', $entityId))
            ->when($workflowStep instanceof AiWorkflowStep, static fn ($query) => $query->where('workflow_step_id', $workflowStep->id))
            ->when(! $workflowStep && $stepIndex !== null, static fn ($query) => $query->where('step_index', $stepIndex))
            ->latest()
            ->first();

        if ($existing instanceof AiApprovalRequest) {
            return [
                'request' => $this->formatApprovalRequest($existing),
                'meta' => [
                    'workflow_id' => $workflowId,
                    'step_index' => $existing->step_index,
                    'status' => 'already_pending',
                ],
            ];
        }

        $approval = DB::transaction(function () use (
            $companyId,
            $workflowId,
            $workflowStep,
            $stepIndex,
            $entityType,
            $entityId,
            $stepType,
            $approverRole,
            $approverUserId,
            $requestedById,
            $message,
        ): AiApprovalRequest {
            return AiApprovalRequest::query()->create([
                'company_id' => $companyId,
                'workflow_id' => $workflowId,
                'workflow_step_id' => $workflowStep?->id,
                'step_index' => $stepIndex,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'step_type' => $stepType,
                'approver_role' => $approverRole,
                'approver_user_id' => $approverUserId,
                'requested_by' => $requestedById,
                'message' => $message,
                'status' => AiApprovalRequest::STATUS_PENDING,
            ]);
        });

        $approval = $approval->fresh() ?? $approval;

        $workflowModel = $workflow->fresh() ?? $workflow;
        $this->workflowService->refreshWorkflowSnapshot($workflowModel);

        return [
            'request' => $this->formatApprovalRequest($approval),
            'meta' => [
                'workflow_id' => $workflowId,
                'step_index' => $stepIndex,
                'status' => 'created',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleGetQuotesForRfq(int $companyId, array $arguments): array
    {
        $rfqId = $this->coerceId($arguments['rfq_id'] ?? null);

        if ($rfqId === null) {
            throw new AiChatException('rfq_id is required for workspace.get_quotes_for_rfq tool.');
        }

        $limit = $this->sanitizeLimit($arguments['limit'] ?? null, 5, 25);

        $quotes = Quote::query()
            ->forCompany($companyId)
            ->where('rfq_id', $rfqId)
            ->with(['supplier' => static fn ($query) => $query->select(['id', 'name', 'country', 'city'])])
            ->orderByDesc('submitted_at')
            ->limit($limit)
            ->get(['id', 'rfq_id', 'supplier_id', 'status', 'total_price', 'currency', 'lead_time_days', 'submitted_at']);

        return [
            'items' => $quotes->map(static function (Quote $quote): array {
                return [
                    'quote_id' => $quote->id,
                    'rfq_id' => $quote->rfq_id,
                    'supplier' => $quote->supplier ? [
                        'supplier_id' => $quote->supplier->id,
                        'name' => $quote->supplier->name,
                        'location' => trim($quote->supplier->city . ', ' . $quote->supplier->country, ', '),
                    ] : null,
                    'status' => $quote->status,
                    'total_price' => $quote->total_price,
                    'currency' => $quote->currency,
                    'lead_time_days' => $quote->lead_time_days,
                    'submitted_at' => optional($quote->submitted_at)->toIso8601String(),
                ];
            })->all(),
            'meta' => [
                'limit' => $limit,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleGetItem(int $companyId, array $arguments): array
    {
        $partId = $this->coerceId($arguments['item_id'] ?? $arguments['part_id'] ?? null);
        $sku = $this->stringValue($arguments['part_number'] ?? $arguments['sku'] ?? null);

        if ($partId === null && $sku === null) {
            throw new AiChatException('item_id or part_number is required for workspace.get_item tool.');
        }

        $query = Part::query()->forCompany($companyId);

        if ($partId !== null) {
            $query->whereKey($partId);
        } else {
            $query->where('part_number', $sku);
        }

        $part = $query->first([
            'id',
            'part_number',
            'name',
            'description',
            'category',
            'uom',
            'spec',
            'attributes',
            'active',
            'created_at',
            'updated_at',
        ]);

        if (! $part instanceof Part) {
            return ['item' => null];
        }

        $inventorySummary = $this->resolveInventorySummary($companyId, $part->id);
        $preferredSuppliers = $this->resolvePreferredSuppliers($companyId, $part);
        $lastPurchase = $this->resolveLastPurchase($companyId, $part);

        return [
            'item' => [
                'part_id' => $part->id,
                'part_number' => $part->part_number,
                'name' => $part->name,
                'description' => $part->description,
                'category' => $part->category,
                'uom' => $part->uom,
                'status' => $part->active ? 'active' : 'inactive',
                'spec' => $part->spec,
                'attributes' => is_array($part->attributes) ? $part->attributes : null,
                'preferred_suppliers' => $preferredSuppliers,
                'last_purchase' => $lastPurchase,
                'inventory' => $inventorySummary['inventory'],
                'settings' => $inventorySummary['settings'],
                'updated_at' => $this->isoDateTime($part->updated_at),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleGetInventoryItem(int $companyId, array $arguments): array
    {
        $partId = $this->coerceId($arguments['item_id'] ?? $arguments['part_id'] ?? null);
        $sku = isset($arguments['sku']) ? trim((string) $arguments['sku']) : null;

        $partQuery = Part::query()->forCompany($companyId);

        if ($partId !== null) {
            $partQuery->whereKey($partId);
        } elseif ($sku !== null && $sku !== '') {
            $partQuery->where('part_number', $sku);
        } else {
            throw new AiChatException('item_id or sku is required for workspace.get_inventory_item tool.');
        }

        $part = $partQuery->first(['id', 'part_number', 'name', 'uom', 'category']);

        if (! $part instanceof Part) {
            return ['item' => null];
        }

        $inventorySummary = $this->resolveInventorySummary($companyId, $part->id);

        return [
            'item' => [
                'part_id' => $part->id,
                'part_number' => $part->part_number,
                'name' => $part->name,
                'uom' => $part->uom,
                'category' => $part->category,
                'inventory' => $inventorySummary['inventory'],
                'settings' => $inventorySummary['settings'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleLowStock(int $companyId, array $arguments): array
    {
        $limit = $this->sanitizeLimit($arguments['limit'] ?? null, 5, 25);

        $settings = InventorySetting::query()
            ->forCompany($companyId)
            ->whereNotNull('min_qty')
            ->with(['part' => static fn ($query) => $query->select(['id', 'part_number', 'name', 'uom'])])
            ->limit(100)
            ->get(['id', 'part_id', 'min_qty', 'safety_stock']);

        $records = [];

        foreach ($settings as $setting) {
            if ($setting->part === null) {
                continue;
            }

            $onHand = (float) Inventory::query()
                ->forCompany($companyId)
                ->where('part_id', $setting->part_id)
                ->sum('on_hand');

            $target = (float) ($setting->min_qty ?? 0.0);
            $ratio = $target > 0 ? ($onHand / $target) : null;

            if ($ratio !== null && $ratio >= 1) {
                continue;
            }

            $records[] = [
                'part_id' => $setting->part_id,
                'part_number' => $setting->part->part_number,
                'name' => $setting->part->name,
                'uom' => $setting->part->uom,
                'on_hand' => $onHand,
                'safety_stock' => $setting->safety_stock,
                'min_qty' => $setting->min_qty,
                'coverage_ratio' => $ratio,
            ];
        }

        usort($records, static function (array $left, array $right): int {
            return ($left['coverage_ratio'] ?? 0) <=> ($right['coverage_ratio'] ?? 0);
        });

        return [
            'items' => array_slice($records, 0, $limit),
            'meta' => [
                'limit' => $limit,
                'total_candidates' => count($records),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleGetAwards(int $companyId, array $arguments): array
    {
        $rfqId = $this->coerceId($arguments['rfq_id'] ?? null);

        if ($rfqId === null) {
            throw new AiChatException('rfq_id is required for workspace.get_awards tool.');
        }

        $limit = $this->sanitizeLimit($arguments['limit'] ?? null, 5, 25);

        $awards = RfqItemAward::query()
            ->forCompany($companyId)
            ->where('rfq_id', $rfqId)
            ->with([
                'supplier' => static fn ($query) => $query->select(['id', 'name'])->withTrashed(),
            ])
            ->orderByDesc('awarded_at')
            ->limit($limit)
            ->get(['id', 'rfq_id', 'supplier_id', 'awarded_qty', 'status', 'awarded_at']);

        return [
            'items' => $awards->map(static function (RfqItemAward $award): array {
                return [
                    'award_id' => $award->id,
                    'rfq_id' => $award->rfq_id,
                    'supplier' => $award->supplier ? [
                        'supplier_id' => $award->supplier->id,
                        'name' => $award->supplier->name,
                    ] : null,
                    'awarded_qty' => $award->awarded_qty,
                    'status' => $award->status?->value ?? $award->status,
                    'awarded_at' => optional($award->awarded_at)->toIso8601String(),
                ];
            })->all(),
            'meta' => [
                'limit' => $limit,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleSearchReceipts(int $companyId, array $arguments): array
    {
        $limit = $this->sanitizeLimit($arguments['limit'] ?? null, 5, 25);
        $keyword = $this->stringValue($arguments['query'] ?? null) ?? '';
        $statuses = $this->sanitizeReceiptStatuses($arguments['statuses'] ?? null);

        $query = GoodsReceiptNote::query()
            ->forCompany($companyId)
            ->with([
                'purchaseOrder' => static fn ($builder) => $builder->select(['id', 'company_id', 'po_number', 'supplier_id'])->with([
                    'supplier' => static fn ($supplierQuery) => $supplierQuery->withTrashed()->select(['id', 'name'])
                ]),
            ]);

        if ($keyword !== '') {
            $query->where(static function (Builder $builder) use ($keyword): void {
                $like = '%' . $keyword . '%';
                $builder->where('number', 'like', $like)
                    ->orWhereHas('purchaseOrder', static function (Builder $poQuery) use ($like): void {
                        $poQuery->where('po_number', 'like', $like)
                            ->orWhereHas('supplier', static function (Builder $supplierQuery) use ($like): void {
                                $supplierQuery->where('name', 'like', $like);
                            });
                    });
            });
        }

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        $totalCount = (clone $query)->count();
        $statusCounts = (clone $query)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn ($count) => (int) $count)
            ->all();

        $receipts = (clone $query)
            ->withSum('lines as total_received_qty', 'received_qty')
            ->orderByDesc('inspected_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id',
                'purchase_order_id',
                'number',
                'status',
                'inspected_at',
            ]);

        $items = $receipts->map(function (GoodsReceiptNote $receipt): array {
            $purchaseOrder = $receipt->purchaseOrder;
            $supplier = $purchaseOrder?->supplier;

            return [
                'receipt_id' => $receipt->id,
                'receipt_number' => $receipt->number,
                'status' => $receipt->status,
                'received_at' => $this->isoDateTime($receipt->inspected_at ?? $receipt->created_at),
                'po' => $purchaseOrder ? [
                    'purchase_order_id' => $purchaseOrder->id,
                    'po_number' => $purchaseOrder->po_number,
                ] : null,
                'supplier' => $supplier ? [
                    'supplier_id' => $supplier->id,
                    'name' => $supplier->name,
                ] : null,
                'total_received_qty' => (int) ($receipt->total_received_qty ?? 0),
            ];
        })->all();

        return [
            'items' => $items,
            'meta' => [
                'limit' => $limit,
                'query' => $keyword,
                'statuses' => $statuses,
                'total_count' => $totalCount,
                'status_counts' => $statusCounts,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleGetReceipts(int $companyId, array $arguments): array
    {
        $limit = $this->sanitizeLimit($arguments['limit'] ?? null, 2, 10);
        $filters = isset($arguments['filters']) && is_array($arguments['filters']) ? $arguments['filters'] : [];
        $context = isset($arguments['context']) && is_array($arguments['context']) ? $arguments['context'] : [];
        $supplierName = $this->stringValue($filters['supplier_name'] ?? null) ?? 'Preferred Supplier';
        $statusPool = self::RECEIPT_STATUSES;
        $statusCount = max(count($statusPool), 1);

        // Placeholder data keeps Copilot responses deterministic until live workspace feeds arrive.
        $items = [];

        for ($index = 0; $index < $limit; $index++) {
            $items[] = [
                'id' => sprintf('receipt-%d', $index + 1),
                'receipt_number' => sprintf('GRN-%05d', 1200 + $index + 1),
                'supplier_name' => $supplierName,
                'status' => $statusPool[$index % $statusCount],
                'total_amount' => round(1250.25 + ($index * 137.5), 2),
                'created_at' => Carbon::now()->subDays($index)->toIso8601String(),
            ];
        }

        return [
            'items' => $items,
            'meta' => [
                'limit' => $limit,
                'filters' => $filters,
                'context' => $context,
                'source' => 'placeholder',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleSearchInvoices(int $companyId, array $arguments): array
    {
        $limit = $this->sanitizeLimit($arguments['limit'] ?? null, 10, 50);
        $keyword = $this->stringValue($arguments['query'] ?? null) ?? '';
        $statuses = $this->sanitizeInvoiceStatuses($arguments['statuses'] ?? null);
        $dateFrom = $this->parseDateValue($arguments['date_from'] ?? $arguments['due_from'] ?? null);
        $dateTo = $this->parseDateValue($arguments['date_to'] ?? $arguments['due_to'] ?? null);

        $query = Invoice::query()->forCompany($companyId);

        if ($keyword !== '') {
            $query->where(static function (Builder $builder) use ($keyword): void {
                $like = '%' . $keyword . '%';
                $builder->where('invoice_number', 'like', $like)
                    ->orWhereHas('supplier', static function (Builder $supplierQuery) use ($like): void {
                        $supplierQuery->where('name', 'like', $like);
                    })
                    ->orWhereHas('purchaseOrder', static function (Builder $poQuery) use ($like): void {
                        $poQuery->where('po_number', 'like', $like);
                    });
            });
        }

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        if ($dateFrom !== null) {
            $query->whereDate('due_date', '>=', $dateFrom->toDateString());
        }

        if ($dateTo !== null) {
            $query->whereDate('due_date', '<=', $dateTo->toDateString());
        }

        $totalCount = (clone $query)->count();

        $statusCounts = (clone $query)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn ($count) => (int) $count)
            ->all();

        $invoices = (clone $query)
            ->with([
                'supplier' => static fn ($builder) => $builder->withTrashed()->select(['id', 'name']),
                'purchaseOrder' => static fn ($builder) => $builder->select(['id', 'company_id', 'po_number']),
                'matches' => static fn ($builder) => $builder
                    ->select(['id', 'invoice_id', 'result'])
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit(5),
            ])
            ->orderByDesc('due_date')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id',
                'purchase_order_id',
                'supplier_id',
                'invoice_number',
                'status',
                'currency',
                'total',
                'total_minor',
                'due_date',
                'matched_status',
            ]);

        $items = $invoices->map(function (Invoice $invoice): array {
            $currency = strtoupper($invoice->currency ?? 'USD');

            return [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'currency' => $currency,
                'total' => $this->formatMoney($invoice->total, $invoice->total_minor, $currency),
                'due_date' => $this->isoDateTime($invoice->due_date),
                'supplier' => $invoice->supplier ? [
                    'supplier_id' => $invoice->supplier->id,
                    'name' => $invoice->supplier->name,
                ] : null,
                'purchase_order' => $invoice->purchaseOrder ? [
                    'purchase_order_id' => $invoice->purchaseOrder->id,
                    'po_number' => $invoice->purchaseOrder->po_number,
                ] : null,
                'exceptions' => $this->resolveInvoiceExceptions($invoice),
            ];
        })->all();

        return [
            'items' => $items,
            'meta' => [
                'limit' => $limit,
                'query' => $keyword,
                'statuses' => $statuses,
                'date_from' => $dateFrom?->toIso8601String(),
                'date_to' => $dateTo?->toIso8601String(),
                'total_count' => $totalCount,
                'status_counts' => $statusCounts,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleGetInvoices(int $companyId, array $arguments): array
    {
        $limit = $this->sanitizeLimit($arguments['limit'] ?? null, 3, 15);
        $filters = isset($arguments['filters']) && is_array($arguments['filters']) ? $arguments['filters'] : [];
        $context = isset($arguments['context']) && is_array($arguments['context']) ? $arguments['context'] : [];
        $supplierName = $this->stringValue($filters['supplier_name'] ?? null) ?? 'Strategic Supplier';
        $statusPool = self::INVOICE_STATUSES;
        $statusCount = max(count($statusPool), 1);

        // Placeholder data keeps Copilot flows functional when tenants lack invoices.
        $items = [];

        for ($index = 0; $index < $limit; $index++) {
            $items[] = [
                'id' => sprintf('invoice-%d', $index + 1),
                'invoice_number' => sprintf('INV-%05d', 9000 + $index + 1),
                'supplier_name' => $supplierName,
                'status' => $statusPool[$index % $statusCount],
                'total_amount' => round(2420.75 + ($index * 210.5), 2),
                'created_at' => Carbon::now()->subDays($index + 1)->toIso8601String(),
            ];
        }

        return [
            'items' => $items,
            'meta' => [
                'limit' => $limit,
                'filters' => $filters,
                'context' => $context,
                'source' => 'placeholder',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleSearchDisputes(int $companyId, array $arguments): array
    {
        $limit = $this->sanitizeLimit($arguments['limit'] ?? null, 10, 25);
        $keyword = $this->stringValue($arguments['query'] ?? null) ?? '';
        $statuses = $this->sanitizeDisputeStatuses($arguments['statuses'] ?? null);
        $invoiceId = $this->coerceId($arguments['invoice_id'] ?? null);
        $purchaseOrderId = $this->coerceId($arguments['purchase_order_id'] ?? $arguments['po_id'] ?? null);
        $dueFrom = $this->parseDateValue($arguments['due_from'] ?? null);
        $dueTo = $this->parseDateValue($arguments['due_to'] ?? null);

        $query = InvoiceDisputeTask::query()
            ->forCompany($companyId)
            ->with([
                'invoice' => static fn ($builder) => $builder->select(['id', 'company_id', 'invoice_number', 'status', 'matched_status', 'due_date']),
                'purchaseOrder' => static fn ($builder) => $builder->select(['id', 'company_id', 'po_number', 'status']),
                'goodsReceiptNote' => static fn ($builder) => $builder->select(['id', 'company_id', 'number', 'status', 'inspected_at']),
            ]);

        if ($keyword !== '') {
            $query->where(static function (Builder $builder) use ($keyword): void {
                $like = '%' . $keyword . '%';
                $builder->where('summary', 'like', $like)
                    ->orWhereJsonContains('reason_codes', $keyword)
                    ->orWhereHas('invoice', static function (Builder $invoiceQuery) use ($like): void {
                        $invoiceQuery->where('invoice_number', 'like', $like);
                    })
                    ->orWhereHas('purchaseOrder', static function (Builder $poQuery) use ($like): void {
                        $poQuery->where('po_number', 'like', $like);
                    })
                    ->orWhereHas('goodsReceiptNote', static function (Builder $receiptQuery) use ($like): void {
                        $receiptQuery->where('number', 'like', $like);
                    });
            });
        }

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        if ($invoiceId !== null) {
            $query->where('invoice_id', $invoiceId);
        }

        if ($purchaseOrderId !== null) {
            $query->where('purchase_order_id', $purchaseOrderId);
        }

        if ($dueFrom !== null) {
            $query->whereDate('due_at', '>=', $dueFrom->toDateString());
        }

        if ($dueTo !== null) {
            $query->whereDate('due_at', '<=', $dueTo->toDateString());
        }

        $totalCount = (clone $query)->count();
        $statusCounts = $this->summarizeStatusCounts(clone $query);

        $tasks = (clone $query)
            ->orderByDesc('due_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id',
                'invoice_id',
                'purchase_order_id',
                'goods_receipt_note_id',
                'resolution_type',
                'status',
                'summary',
                'owner_role',
                'requires_hold',
                'due_at',
                'reason_codes',
                'created_at',
                'updated_at',
                'resolved_at',
            ]);

        return [
            'items' => $tasks->map(function (InvoiceDisputeTask $task): array {
                return $this->transformDisputeTaskSummary($task);
            })->all(),
            'meta' => [
                'limit' => $limit,
                'query' => $keyword,
                'statuses' => $statuses,
                'invoice_id' => $invoiceId,
                'purchase_order_id' => $purchaseOrderId,
                'due_from' => $dueFrom?->toIso8601String(),
                'due_to' => $dueTo?->toIso8601String(),
                'total_count' => $totalCount,
                'status_counts' => $statusCounts,
            ],
        ];
    }


    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleGetInvoice(int $companyId, array $arguments): array
    {
        $invoiceId = $this->coerceId($arguments['invoice_id'] ?? $arguments['id'] ?? null);
        $invoiceNumber = $this->stringValue($arguments['invoice_number'] ?? null);

        if ($invoiceId === null && $invoiceNumber === null) {
            throw new AiChatException('invoice_id or invoice_number is required for workspace.get_invoice tool.');
        }

        $query = Invoice::query()
            ->forCompany($companyId)
            ->with([
                'supplier' => static fn ($builder) => $builder->withTrashed()->select(['id', 'name']),
                'purchaseOrder' => static fn ($builder) => $builder->select(['id', 'company_id', 'po_number']),
                'lines' => static fn ($builder) => $builder
                    ->select([
                        'id',
                        'invoice_id',
                        'po_line_id',
                        'description',
                        'quantity',
                        'uom',
                        'unit_price',
                        'unit_price_minor',
                        'line_total_minor',
                    ])
                    ->orderBy('po_line_id')
                    ->orderBy('id')
                    ->limit(10),
                'matches' => static fn ($builder) => $builder
                    ->select(['id', 'invoice_id', 'result', 'details'])
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit(10),
                'payments' => static fn ($builder) => $builder
                    ->select([
                        'id',
                        'invoice_id',
                        'amount',
                        'amount_minor',
                        'currency',
                        'payment_reference',
                        'payment_method',
                        'paid_at',
                    ])
                    ->orderByDesc('paid_at')
                    ->orderByDesc('id')
                    ->limit(5),
            ])
            ->withCount(['lines as lines_total']);

        if ($invoiceId !== null) {
            $query->whereKey($invoiceId);
        } else {
            $query->where('invoice_number', $invoiceNumber);
        }

        $invoice = $query->first([
            'id',
            'purchase_order_id',
            'supplier_id',
            'invoice_number',
            'status',
            'currency',
            'subtotal',
            'subtotal_minor',
            'tax_amount',
            'tax_minor',
            'total',
            'total_minor',
            'invoice_date',
            'due_date',
            'matched_status',
        ]);

        if (! $invoice instanceof Invoice) {
            return [
                'invoice' => null,
                'meta' => [
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoiceNumber,
                    'note' => 'Invoice not found for this workspace.',
                ],
            ];
        }

        $currency = strtoupper($invoice->currency ?? 'USD');
        $minorUnit = $this->currencyMinorUnit($currency);
        $precision = 10 ** $minorUnit;

        $paymentCollection = $invoice->payments;
        $paidMinor = (int) $paymentCollection->sum(static fn ($payment) => (int) ($payment->amount_minor ?? 0));
        $totalMinor = $invoice->total_minor;
        $openMinor = $totalMinor !== null ? max(0, $totalMinor - $paidMinor) : null;
        $openAmount = $openMinor !== null ? round($openMinor / $precision, $minorUnit) : null;

        $lines = $invoice->lines->map(function (InvoiceLine $line) use ($currency): array {
            return [
                'line_id' => $line->id,
                'po_line_id' => $line->po_line_id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'uom' => $line->uom,
                'unit_price' => $this->formatMoney($line->unit_price, $line->unit_price_minor, $currency),
                'unit_price_minor' => $line->unit_price_minor,
                'line_total' => $line->line_total_minor !== null
                    ? $this->formatMoney(null, $line->line_total_minor, $currency)
                    : null,
                'line_total_minor' => $line->line_total_minor,
            ];
        })->all();

        $matches = $invoice->matches->map(static function ($match): array {
            return [
                'invoice_match_id' => $match->id,
                'result' => $match->result,
                'details' => is_array($match->details) ? $match->details : [],
            ];
        })->all();

        $payments = $paymentCollection->map(static function ($payment): array {
            return [
                'payment_id' => $payment->id,
                'amount' => $payment->amount !== null ? (float) $payment->amount : null,
                'amount_minor' => $payment->amount_minor,
                'currency' => $payment->currency,
                'payment_reference' => $payment->payment_reference,
                'payment_method' => $payment->payment_method,
                'paid_at' => optional($payment->paid_at)->toIso8601String(),
            ];
        })->all();

        $purchaseOrder = $invoice->purchaseOrder;
        $supplier = $invoice->supplier;

        return [
            'invoice' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'currency' => $currency,
                'invoice_date' => $this->isoDateTime($invoice->invoice_date),
                'due_date' => $this->isoDateTime($invoice->due_date),
                'supplier' => $supplier ? [
                    'supplier_id' => $supplier->id,
                    'name' => $supplier->name,
                ] : null,
                'purchase_order' => $purchaseOrder ? [
                    'purchase_order_id' => $purchaseOrder->id,
                    'po_number' => $purchaseOrder->po_number,
                ] : null,
                'exceptions' => $this->resolveInvoiceExceptions($invoice),
                'matched_status' => $invoice->matched_status,
                'match_summary' => $this->buildInvoiceMatchSummary($invoice),
                'totals' => [
                    'subtotal' => $this->formatMoney($invoice->subtotal, $invoice->subtotal_minor, $currency),
                    'tax' => $this->formatMoney($invoice->tax_amount, $invoice->tax_minor, $currency),
                    'grand_total' => $this->formatMoney($invoice->total, $invoice->total_minor, $currency),
                    'grand_total_minor' => $invoice->total_minor,
                    'paid_minor' => $paidMinor,
                    'open_balance_minor' => $openMinor,
                    'open_balance' => $openAmount,
                ],
                'lines' => $lines,
                'line_sample_limit' => 10,
                'line_total_count' => (int) ($invoice->lines_total ?? count($lines)),
                'matches' => $matches,
                'payments' => $payments,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleGetDispute(int $companyId, array $arguments): array
    {
        $disputeId = $this->coerceId($arguments['dispute_id'] ?? $arguments['id'] ?? null);
        $invoiceId = $this->coerceId($arguments['invoice_id'] ?? null);

        if ($disputeId === null && $invoiceId === null) {
            throw new AiChatException('dispute_id or invoice_id is required for workspace.get_dispute tool.');
        }

        $query = InvoiceDisputeTask::query()
            ->forCompany($companyId)
            ->with([
                'invoice' => static fn ($builder) => $builder->select(['id', 'company_id', 'invoice_number', 'status', 'matched_status', 'due_date']),
                'purchaseOrder' => static fn ($builder) => $builder->select(['id', 'company_id', 'po_number', 'status']),
                'goodsReceiptNote' => static fn ($builder) => $builder->select(['id', 'company_id', 'number', 'status', 'inspected_at']),
                'creator' => static fn ($builder) => $builder->select(['id', 'name']),
                'resolver' => static fn ($builder) => $builder->select(['id', 'name']),
            ]);

        if ($disputeId !== null) {
            $query->whereKey($disputeId);
        } elseif ($invoiceId !== null) {
            $query->where('invoice_id', $invoiceId)
                ->orderByDesc('created_at');
        }

        $task = $query->first([
            'id',
            'invoice_id',
            'purchase_order_id',
            'goods_receipt_note_id',
            'resolution_type',
            'status',
            'summary',
            'owner_role',
            'requires_hold',
            'due_at',
            'actions',
            'impacted_lines',
            'next_steps',
            'notes',
            'reason_codes',
            'created_by',
            'resolved_by',
            'created_at',
            'updated_at',
            'resolved_at',
        ]);

        if (! $task instanceof InvoiceDisputeTask) {
            return [
                'dispute' => null,
                'meta' => [
                    'dispute_id' => $disputeId,
                    'invoice_id' => $invoiceId,
                    'note' => 'Dispute not found for this workspace.',
                ],
            ];
        }

        return [
            'dispute' => $this->transformDisputeTaskDetail($task),
        ];
    }

    private function transformDisputeTaskSummary(InvoiceDisputeTask $task): array
    {
        $invoice = $task->invoice;
        $purchaseOrder = $task->purchaseOrder;
        $receipt = $task->goodsReceiptNote;

        return [
            'dispute_id' => $task->id,
            'invoice_id' => $task->invoice_id,
            'purchase_order_id' => $task->purchase_order_id,
            'goods_receipt_note_id' => $task->goods_receipt_note_id,
            'resolution_type' => $task->resolution_type,
            'status' => $task->status,
            'summary' => $task->summary,
            'owner_role' => $task->owner_role,
            'requires_hold' => (bool) $task->requires_hold,
            'due_at' => $this->isoDateTime($task->due_at),
            'reason_codes' => array_slice($this->sanitizeStringArray($task->reason_codes ?? null), 0, 20),
            'invoice' => $invoice ? $this->formatDisputeInvoice($invoice) : null,
            'purchase_order' => $purchaseOrder ? $this->formatDisputePurchaseOrder($purchaseOrder) : null,
            'receipt' => $receipt ? $this->formatDisputeReceipt($receipt) : null,
            'created_at' => $this->isoDateTime($task->created_at),
            'updated_at' => $this->isoDateTime($task->updated_at),
            'resolved_at' => $this->isoDateTime($task->resolved_at),
        ];
    }

    private function transformDisputeTaskDetail(InvoiceDisputeTask $task): array
    {
        $payload = $this->transformDisputeTaskSummary($task);

        $payload['actions'] = $this->formatDisputeActionsPayload($task->actions ?? null);
        $payload['impacted_lines'] = $this->formatDisputeImpactsPayload($task->impacted_lines ?? null);
        $payload['next_steps'] = array_slice($this->sanitizeStringArray($task->next_steps ?? null), 0, 20);
        $payload['notes'] = array_slice($this->sanitizeStringArray($task->notes ?? null), 0, 20);
        $payload['creator'] = $task->creator ? [
            'user_id' => $task->creator->id,
            'name' => $task->creator->name,
        ] : null;
        $payload['resolver'] = $task->resolver ? [
            'user_id' => $task->resolver->id,
            'name' => $task->resolver->name,
        ] : null;

        return $payload;
    }

    private function formatDisputeInvoice(Invoice $invoice): array
    {
        return [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'matched_status' => $invoice->matched_status,
            'due_date' => $this->isoDateTime($invoice->due_date),
        ];
    }

    private function formatDisputePurchaseOrder(PurchaseOrder $purchaseOrder): array
    {
        return [
            'purchase_order_id' => $purchaseOrder->id,
            'po_number' => $purchaseOrder->po_number,
            'status' => $purchaseOrder->status,
        ];
    }

    private function formatDisputeReceipt(GoodsReceiptNote $receipt): array
    {
        return [
            'goods_receipt_note_id' => $receipt->id,
            'number' => $receipt->number,
            'status' => $receipt->status,
            'inspected_at' => $this->isoDateTime($receipt->inspected_at),
        ];
    }

    private function formatDisputeActionsPayload(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $actions = [];

        foreach ($value as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $type = $this->stringValue($entry['type'] ?? null);
            $description = $this->stringValue($entry['description'] ?? null);

            if ($type === null || $description === null) {
                continue;
            }

            $action = [
                'type' => $type,
                'description' => $description,
                'requires_hold' => (bool) ($entry['requires_hold'] ?? false),
            ];

            $ownerRole = $this->stringValue($entry['owner_role'] ?? null);

            if ($ownerRole !== null) {
                $action['owner_role'] = $ownerRole;
            }

            if (isset($entry['due_in_days']) && is_numeric($entry['due_in_days'])) {
                $action['due_in_days'] = (int) $entry['due_in_days'];
            }

            $actions[] = $action;

            if (count($actions) >= 25) {
                break;
            }
        }

        return $actions;
    }

    private function formatDisputeImpactsPayload(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $impacts = [];

        foreach ($value as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $reference = $this->stringValue($entry['reference'] ?? $entry['line_reference'] ?? null);
            $issue = $this->stringValue($entry['issue'] ?? null);
            $recommendation = $this->stringValue($entry['recommended_action'] ?? null);

            if ($reference === null || $issue === null || $recommendation === null) {
                continue;
            }

            $impact = [
                'reference' => $reference,
                'issue' => $issue,
                'recommended_action' => $recommendation,
            ];

            $severity = $this->stringValue($entry['severity'] ?? null);

            if ($severity !== null) {
                $impact['severity'] = $severity;
            }

            if (isset($entry['variance']) && is_numeric($entry['variance'])) {
                $impact['variance'] = (float) $entry['variance'];
            }

            $impacts[] = $impact;

            if (count($impacts) >= 25) {
                break;
            }
        }

        return $impacts;
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleSearchPayments(int $companyId, array $arguments): array
    {
        $limit = $this->sanitizeLimit($arguments['limit'] ?? null, 10, 50);
        $keyword = $this->stringValue($arguments['query'] ?? null) ?? '';
        $statuses = $this->sanitizePaymentStatuses($arguments['statuses'] ?? null);
        $dateFrom = $this->parseDateValue($arguments['date_from'] ?? null);
        $dateTo = $this->parseDateValue($arguments['date_to'] ?? null);

        $query = InvoicePayment::query()
            ->forCompany($companyId)
            ->with([
                'invoice' => static fn ($builder) => $builder
                    ->select(['id', 'company_id', 'invoice_number', 'status', 'supplier_id', 'purchase_order_id'])
                    ->with([
                        'supplier' => static fn ($supplierQuery) => $supplierQuery->withTrashed()->select(['id', 'name']),
                        'purchaseOrder' => static fn ($poQuery) => $poQuery->select(['id', 'company_id', 'po_number']),
                    ]),
            ]);

        if ($keyword !== '') {
            $query->where(static function (Builder $builder) use ($keyword): void {
                $like = '%' . $keyword . '%';
                $builder->where('payment_reference', 'like', $like)
                    ->orWhere('payment_method', 'like', $like)
                    ->orWhereHas('invoice', static function (Builder $invoiceQuery) use ($like): void {
                        $invoiceQuery->where('invoice_number', 'like', $like)
                            ->orWhereHas('supplier', static function (Builder $supplierQuery) use ($like): void {
                                $supplierQuery->where('name', 'like', $like);
                            })
                            ->orWhereHas('purchaseOrder', static function (Builder $poQuery) use ($like): void {
                                $poQuery->where('po_number', 'like', $like);
                            });
                    });
            });
        }

        if ($dateFrom !== null) {
            $dateString = $dateFrom->toDateString();
            $query->where(static function (Builder $builder) use ($dateString): void {
                $builder->whereNotNull('paid_at')->whereDate('paid_at', '>=', $dateString)
                    ->orWhere(static function (Builder $pendingQuery) use ($dateString): void {
                        $pendingQuery->whereNull('paid_at')->whereDate('created_at', '>=', $dateString);
                    });
            });
        }

        if ($dateTo !== null) {
            $dateString = $dateTo->toDateString();
            $query->where(static function (Builder $builder) use ($dateString): void {
                $builder->whereNotNull('paid_at')->whereDate('paid_at', '<=', $dateString)
                    ->orWhere(static function (Builder $pendingQuery) use ($dateString): void {
                        $pendingQuery->whereNull('paid_at')->whereDate('created_at', '<=', $dateString);
                    });
            });
        }

        $statusCounts = [
            'pending' => (clone $query)->whereNull('paid_at')->count(),
            'paid' => (clone $query)->whereNotNull('paid_at')->count(),
        ];

        if ($statuses !== []) {
            $query->where(static function (Builder $builder) use ($statuses): void {
                $hasPending = in_array('pending', $statuses, true);
                $hasPaid = in_array('paid', $statuses, true);
                $applied = false;

                if ($hasPending) {
                    $builder->whereNull('paid_at');
                    $applied = true;
                }

                if ($hasPaid) {
                    if ($applied) {
                        $builder->orWhereNotNull('paid_at');
                    } else {
                        $builder->whereNotNull('paid_at');
                    }
                }
            });
        }

        $totalCount = (clone $query)->count();

        $payments = (clone $query)
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get([
                'id',
                'invoice_id',
                'amount',
                'amount_minor',
                'currency',
                'paid_at',
                'payment_reference',
                'payment_method',
                'note',
                'created_at',
            ]);

        $items = $payments->map(function (InvoicePayment $payment): array {
            $currency = strtoupper($payment->currency ?? 'USD');
            $invoice = $payment->invoice;
            $supplier = $invoice?->supplier;

            return [
                'payment_id' => $payment->id,
                'payment_reference' => $payment->payment_reference,
                'status' => $this->resolvePaymentStatus($payment->paid_at),
                'currency' => $currency,
                'amount' => $this->formatMoney($payment->amount, $payment->amount_minor, $currency),
                'amount_minor' => $payment->amount_minor,
                'payment_method' => $payment->payment_method,
                'paid_at' => $this->isoDateTime($payment->paid_at ?? $payment->created_at),
                'note' => $payment->note,
                'invoice' => $invoice ? [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $invoice->status,
                ] : null,
                'supplier' => $supplier ? [
                    'supplier_id' => $supplier->id,
                    'name' => $supplier->name,
                ] : null,
            ];
        })->all();

        return [
            'items' => $items,
            'meta' => [
                'limit' => $limit,
                'query' => $keyword,
                'statuses' => $statuses,
                'date_from' => $dateFrom?->toIso8601String(),
                'date_to' => $dateTo?->toIso8601String(),
                'total_count' => $totalCount,
                'status_counts' => $statusCounts,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleGetPayment(int $companyId, array $arguments): array
    {
        $paymentId = $this->coerceId($arguments['payment_id'] ?? null);
        $paymentReference = $this->stringValue($arguments['payment_reference'] ?? null);

        if ($paymentId === null && $paymentReference === null) {
            throw new AiChatException('payment_id or payment_reference is required for workspace.get_payment tool.');
        }

        $query = InvoicePayment::query()
            ->forCompany($companyId)
            ->with([
                'invoice' => static fn ($builder) => $builder
                    ->select(['id', 'company_id', 'invoice_number', 'status', 'supplier_id', 'purchase_order_id'])
                    ->with([
                        'supplier' => static fn ($supplierQuery) => $supplierQuery->withTrashed()->select(['id', 'name']),
                        'purchaseOrder' => static fn ($poQuery) => $poQuery->select(['id', 'company_id', 'po_number']),
                    ]),
            ]);

        if ($paymentId !== null) {
            $query->whereKey($paymentId);
        } else {
            $query->where('payment_reference', $paymentReference);
        }

        $payment = $query->first([
            'id',
            'invoice_id',
            'amount',
            'amount_minor',
            'currency',
            'paid_at',
            'payment_reference',
            'payment_method',
            'note',
            'created_at',
            'updated_at',
        ]);

        if (! $payment instanceof InvoicePayment) {
            return [
                'payment' => null,
                'meta' => [
                    'payment_id' => $paymentId,
                    'payment_reference' => $paymentReference,
                    'note' => 'Payment not found for this workspace.',
                ],
            ];
        }

        $currency = strtoupper($payment->currency ?? 'USD');
        $invoice = $payment->invoice;
        $supplier = $invoice?->supplier;

        return [
            'payment' => [
                'payment_id' => $payment->id,
                'payment_reference' => $payment->payment_reference,
                'status' => $this->resolvePaymentStatus($payment->paid_at),
                'currency' => $currency,
                'amount' => $this->formatMoney($payment->amount, $payment->amount_minor, $currency),
                'amount_minor' => $payment->amount_minor,
                'payment_method' => $payment->payment_method,
                'paid_at' => $this->isoDateTime($payment->paid_at ?? $payment->created_at),
                'note' => $payment->note,
                'invoice' => $invoice ? [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $invoice->status,
                    'purchase_order' => $invoice->purchaseOrder ? [
                        'purchase_order_id' => $invoice->purchaseOrder->id,
                        'po_number' => $invoice->purchaseOrder->po_number,
                    ] : null,
                ] : null,
                'supplier' => $supplier ? [
                    'supplier_id' => $supplier->id,
                    'name' => $supplier->name,
                ] : null,
                'created_at' => $this->isoDateTime($payment->created_at),
                'updated_at' => $this->isoDateTime($payment->updated_at),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleSearchContracts(int $companyId, array $arguments): array
    {
        $limit = $this->sanitizeLimit($arguments['limit'] ?? null, 10, 25);
        $keyword = $this->stringValue($arguments['query'] ?? null) ?? '';
        $statuses = $this->sanitizeLooseStatuses($arguments['statuses'] ?? null);
        $dateFrom = $this->parseDateValue($arguments['date_from'] ?? null);
        $dateTo = $this->parseDateValue($arguments['date_to'] ?? null);

        $query = Document::query()
            ->forCompany($companyId)
            ->where('category', self::CONTRACT_CATEGORY)
            ->with([
                'documentable' => static function (MorphTo $morphTo): void {
                    $morphTo->morphWith([
                        Supplier::class => function ($builder): void {
                            $builder->withTrashed()->select(['id', 'name', 'city', 'country']);
                        },
                        PurchaseOrder::class => [
                            'supplier' => static fn ($builder) => $builder->withTrashed()->select(['id', 'name', 'city', 'country']),
                        ],
                    ]);
                },
            ]);

        if ($keyword !== '') {
            $query->where(static function (Builder $builder) use ($keyword): void {
                $like = '%' . $keyword . '%';
                $builder->where('filename', 'like', $like)
                    ->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(meta, \'$.contract_number\')) LIKE ?', [$like])
                    ->orWhereHasMorph('documentable', Supplier::class, static function (Builder $supplierQuery) use ($like): void {
                        $supplierQuery->where('name', 'like', $like);
                    })
                    ->orWhereHasMorph('documentable', PurchaseOrder::class, static function (Builder $poQuery) use ($like): void {
                        $poQuery->where('po_number', 'like', $like);
                    });
            });
        }

        if ($dateFrom !== null) {
            $query->whereDate('created_at', '>=', $dateFrom->toDateString());
        }

        if ($dateTo !== null) {
            $query->whereDate('created_at', '<=', $dateTo->toDateString());
        }

        $statusCounts = (clone $query)
            ->selectRaw('LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(meta, \'$.status\')), \'unspecified\')) as status_key, COUNT(*) as aggregate')
            ->groupBy('status_key')
            ->pluck('aggregate', 'status_key')
            ->map(static fn ($count) => (int) $count)
            ->all();

        if ($statuses !== []) {
            $query->where(static function (Builder $builder) use ($statuses): void {
                foreach ($statuses as $index => $status) {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $builder->{$method}('LOWER(JSON_UNQUOTE(JSON_EXTRACT(meta, \'$.status\'))) = ?', [$status]);
                }
            });
        }

        $totalCount = (clone $query)->count();

        $documents = (clone $query)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id',
                'company_id',
                'documentable_type',
                'documentable_id',
                'filename',
                'mime',
                'size_bytes',
                'path',
                'meta',
                'expires_at',
                'created_at',
                'updated_at',
            ]);

        $items = $documents->map(function (Document $document): array {
            return $this->formatContractPayload($document);
        })->all();

        return [
            'items' => $items,
            'meta' => [
                'limit' => $limit,
                'query' => $keyword,
                'statuses' => $statuses,
                'date_from' => $dateFrom?->toIso8601String(),
                'date_to' => $dateTo?->toIso8601String(),
                'total_count' => $totalCount,
                'status_counts' => $statusCounts,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleSearchItems(int $companyId, array $arguments): array
    {
        $limit = $this->sanitizeLimit($arguments['limit'] ?? null, 5, 25);
        $keyword = $this->stringValue($arguments['query'] ?? null) ?? '';
        $statuses = $this->sanitizeStatusFilter($arguments['statuses'] ?? null, self::ITEM_STATUSES);
        $categories = $this->sanitizeStringArray($arguments['categories'] ?? null);

        if ($categories === []) {
            $categories = $this->sanitizeStringArray($arguments['category'] ?? null);
        }

        $uom = $this->stringValue($arguments['uom'] ?? null);

        $query = Part::query()
            ->forCompany($companyId);

        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $query->where(static function (Builder $builder) use ($like): void {
                $builder->where('part_number', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('category', 'like', $like);
            });
        }

        if ($categories !== []) {
            $query->whereIn('category', $categories);
        }

        if ($uom !== null) {
            $query->where('uom', $uom);
        }

        $this->applyItemStatusFilter($query, $statuses);

        $totalCount = (clone $query)->count();
        $statusCounts = $this->summarizeItemStatusCounts($query);

        $parts = (clone $query)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id',
                'part_number',
                'name',
                'description',
                'category',
                'uom',
                'active',
                'updated_at',
            ]);

        $items = $parts->map(function (Part $part): array {
            return [
                'part_id' => $part->id,
                'part_number' => $part->part_number,
                'name' => $part->name,
                'description' => $part->description,
                'category' => $part->category,
                'uom' => $part->uom,
                'status' => $part->active ? 'active' : 'inactive',
                'updated_at' => $this->isoDateTime($part->updated_at),
            ];
        })->all();

        return [
            'items' => $items,
            'meta' => [
                'limit' => $limit,
                'query' => $keyword,
                'statuses' => $statuses,
                'categories' => $categories,
                'uom' => $uom,
                'total_count' => $totalCount,
                'status_counts' => $statusCounts,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleGetContract(int $companyId, array $arguments): array
    {
        $contractId = $this->coerceId($arguments['contract_id'] ?? null);
        $contractNumber = $this->stringValue($arguments['contract_number'] ?? null);

        if ($contractId === null && $contractNumber === null) {
            throw new AiChatException('contract_id or contract_number is required for workspace.get_contract tool.');
        }

        $query = Document::query()
            ->forCompany($companyId)
            ->where('category', self::CONTRACT_CATEGORY)
            ->with([
                'documentable' => static function (MorphTo $morphTo): void {
                    $morphTo->morphWith([
                        Supplier::class => function ($builder): void {
                            $builder->withTrashed()->select(['id', 'name', 'city', 'country']);
                        },
                        PurchaseOrder::class => [
                            'supplier' => static fn ($builder) => $builder->withTrashed()->select(['id', 'name', 'city', 'country']),
                        ],
                    ]);
                },
            ]);

        if ($contractId !== null) {
            $query->whereKey($contractId);
        } else {
            $query->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(meta, \'$.contract_number\')) = ?', [$contractNumber]);
        }

        $document = $query->first([
            'id',
            'company_id',
            'documentable_type',
            'documentable_id',
            'filename',
            'mime',
            'size_bytes',
            'path',
            'meta',
            'expires_at',
            'created_at',
            'updated_at',
        ]);

        if (! $document instanceof Document) {
            return [
                'contract' => null,
                'meta' => [
                    'contract_id' => $contractId,
                    'contract_number' => $contractNumber,
                    'note' => 'Contract not found for this workspace.',
                ],
            ];
        }

        $payload = $this->formatContractPayload($document, true);
        $payload['meta'] = is_array($document->meta) ? $document->meta : [];

        return [
            'contract' => $payload,
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleApproveInvoice(int $companyId, array $arguments): array
    {
        $invoiceId = $this->coerceId($arguments['invoice_id'] ?? $arguments['id'] ?? null);
        $invoiceNumber = isset($arguments['invoice_number']) ? trim((string) $arguments['invoice_number']) : null;

        if ($invoiceId === null && ($invoiceNumber === null || $invoiceNumber === '')) {
            throw new AiChatException('invoice_id or invoice_number is required for workspace.approve_invoice tool.');
        }

        $invoiceQuery = Invoice::query()
            ->forCompany($companyId)
            ->with([
                'supplier' => static fn ($builder) => $builder->select(['id', 'name'])->withTrashed(),
                'purchaseOrder' => static fn ($builder) => $builder->select(['id', 'company_id', 'po_number', 'status']),
                'lines' => static fn ($builder) => $builder->select([
                    'id',
                    'invoice_id',
                    'description',
                    'quantity',
                    'uom',
                    'unit_price',
                    'line_total_minor',
                ])->orderBy('id'),
                'payments' => static fn ($builder) => $builder->select([
                    'id',
                    'invoice_id',
                    'amount',
                    'amount_minor',
                    'currency',
                    'payment_reference',
                    'payment_method',
                    'paid_at',
                ])->orderByDesc('paid_at')->orderByDesc('id'),
            ]);

        if ($invoiceId !== null) {
            $invoiceQuery->whereKey($invoiceId);
        } else {
            $invoiceQuery->where('invoice_number', $invoiceNumber);
        }

        $invoice = $invoiceQuery->first();

        if (! $invoice instanceof Invoice) {
            return [
                'invoice' => null,
                'meta' => [
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoiceNumber,
                    'note' => 'Invoice not found for this workspace.',
                ],
            ];
        }

        $currency = strtoupper($invoice->currency ?? 'USD');
        $minorUnit = $this->currencyMinorUnit($currency);
        $precision = 10 ** $minorUnit;

        $payments = $invoice->payments;
        $paidMinor = (int) $payments->sum(static fn ($payment) => (int) ($payment->amount_minor ?? 0));
        $totalMinor = $invoice->total_minor ?? null;
        $openMinor = $totalMinor !== null ? max(0, $totalMinor - $paidMinor) : null;

        $totalAmount = $invoice->total !== null ? (float) $invoice->total : ($totalMinor !== null ? round($totalMinor / $precision, $minorUnit) : null);
        $openAmount = $openMinor !== null ? round($openMinor / $precision, $minorUnit) : ($totalAmount !== null ? max(0.0, $totalAmount - $payments->sum(static fn ($payment) => (float) ($payment->amount ?? 0))) : null);

        $lines = $invoice->lines->map(function ($line) use ($currency, $minorUnit, $precision): array {
            $lineMinor = $line->line_total_minor;
            $lineTotal = $lineMinor !== null ? round($lineMinor / $precision, $minorUnit) : null;

            return [
                'line_id' => $line->id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'uom' => $line->uom,
                'unit_price' => $line->unit_price !== null ? (float) $line->unit_price : null,
                'line_total' => $lineTotal,
                'line_total_minor' => $lineMinor,
                'currency' => $currency,
            ];
        })->take(12)->values()->all();

        $paymentHistory = $payments->take(5)->map(static function ($payment): array {
            return [
                'payment_id' => $payment->id,
                'amount' => $payment->amount !== null ? (float) $payment->amount : null,
                'amount_minor' => $payment->amount_minor,
                'currency' => $payment->currency,
                'payment_reference' => $payment->payment_reference,
                'payment_method' => $payment->payment_method,
                'paid_at' => optional($payment->paid_at)->toIso8601String(),
            ];
        })->all();

        return [
            'invoice' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'currency' => $currency,
                'invoice_date' => optional($invoice->invoice_date)->toIso8601String(),
                'due_date' => optional($invoice->due_date)->toIso8601String(),
                'supplier' => $invoice->supplier ? [
                    'supplier_id' => $invoice->supplier->id,
                    'name' => $invoice->supplier->name,
                ] : null,
                'purchase_order' => $invoice->purchaseOrder ? [
                    'purchase_order_id' => $invoice->purchaseOrder->id,
                    'po_number' => $invoice->purchaseOrder->po_number,
                    'status' => $invoice->purchaseOrder->status,
                ] : null,
                'totals' => [
                    'subtotal' => $invoice->subtotal !== null ? (float) $invoice->subtotal : null,
                    'tax' => $invoice->tax_amount !== null ? (float) $invoice->tax_amount : null,
                    'grand_total' => $totalAmount,
                    'grand_total_minor' => $totalMinor,
                    'paid_minor' => $paidMinor,
                    'open_balance_minor' => $openMinor,
                    'open_balance' => $openAmount,
                ],
                'lines' => $lines,
                'payments' => $paymentHistory,
            ],
            'meta' => [
                'can_mark_paid' => $invoice->status === InvoiceStatus::Approved->value,
                'recommended_next_step' => $openMinor !== null && $openMinor > 0
                    ? 'Capture payment details or request corrections before marking paid.'
                    : 'Invoice already cleared.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleGetReceipt(int $companyId, array $arguments): array
    {
        $receiptId = $this->coerceId($arguments['receipt_id'] ?? null);
        $receiptNumber = $this->stringValue($arguments['receipt_number'] ?? null);

        if ($receiptId === null && $receiptNumber === null) {
            throw new AiChatException('receipt_id or receipt_number is required for workspace.get_receipt tool.');
        }

        $query = GoodsReceiptNote::query()
            ->forCompany($companyId)
            ->with([
                'purchaseOrder' => static fn ($builder) => $builder
                    ->select(['id', 'company_id', 'po_number', 'supplier_id'])
                    ->with(['supplier' => static fn ($supplierQuery) => $supplierQuery->withTrashed()->select(['id', 'name'])]),
                'lines' => static fn ($builder) => $builder
                    ->with(['purchaseOrderLine' => static fn ($lineQuery) => $lineQuery->select([
                        'id', 'purchase_order_id', 'line_no', 'description', 'uom', 'quantity', 'unit_price_minor', 'currency'
                    ])])
                    ->select([
                        'id',
                        'goods_receipt_note_id',
                        'purchase_order_line_id',
                        'received_qty',
                        'accepted_qty',
                        'rejected_qty',
                        'defect_notes',
                    ])
                    ->orderBy('id')
                        ->limit(10),
                    ])
                    ->withCount(['lines as lines_total']);

        if ($receiptId !== null) {
            $query->whereKey($receiptId);
        } else {
            $query->where('number', $receiptNumber);
        }

        $receipt = $query->first(['id', 'purchase_order_id', 'number', 'status', 'inspected_at', 'created_at']);

        if (! $receipt instanceof GoodsReceiptNote) {
            return [
                'receipt' => null,
                'meta' => [
                    'receipt_id' => $receiptId,
                    'receipt_number' => $receiptNumber,
                    'note' => 'Goods receipt not found for this workspace.',
                ],
            ];
        }

        $purchaseOrder = $receipt->purchaseOrder;
        $supplier = $purchaseOrder?->supplier;

        $lines = $receipt->lines->map(function (GoodsReceiptLine $line): array {
            $poLine = $line->purchaseOrderLine;

            return [
                'line_id' => $line->id,
                'po_line_id' => $line->purchase_order_line_id,
                'line_no' => $poLine?->line_no,
                'description' => $poLine?->description,
                'uom' => $poLine?->uom,
                'ordered_qty' => $poLine?->quantity,
                'received_qty' => $line->received_qty,
                'accepted_qty' => $line->accepted_qty,
                'rejected_qty' => $line->rejected_qty,
                'defect_notes' => $line->defect_notes,
            ];
        })->values()->all();

        $totalReceived = array_sum(array_column($lines, 'received_qty'));

        return [
            'receipt' => [
                'receipt_id' => $receipt->id,
                'receipt_number' => $receipt->number,
                'status' => $receipt->status,
                'received_at' => $this->isoDateTime($receipt->inspected_at ?? $receipt->created_at),
                'po' => $purchaseOrder ? [
                    'purchase_order_id' => $purchaseOrder->id,
                    'po_number' => $purchaseOrder->po_number,
                ] : null,
                'supplier' => $supplier ? [
                    'supplier_id' => $supplier->id,
                    'name' => $supplier->name,
                ] : null,
                'total_received_qty' => (int) $totalReceived,
                'lines' => $lines,
                'line_sample_limit' => 10,
                'line_total_count' => (int) ($receipt->lines_total ?? count($lines)),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleHelp(int $companyId, array $arguments): array
    {
        $topic = $this->stringValue($arguments['topic'] ?? $arguments['question'] ?? null) ?? 'workspace overview';
        $locale = $this->sanitizeLocale($arguments['locale'] ?? null);
        $contextBlocks = $this->sanitizeContextBlocks($arguments['context'] ?? null);
        $module = $this->stringValue($arguments['module'] ?? $arguments['resource'] ?? null);
        $actionHint = $this->stringValue($arguments['action'] ?? $arguments['view'] ?? null);
        $entityId = $this->stringValue($arguments['entity_id'] ?? $arguments['id'] ?? $arguments['number'] ?? null);

        $inputs = ['topic' => $topic];

        if ($locale !== null) {
            $inputs['locale'] = $locale;
        }

        if ($contextBlocks !== []) {
            $inputs['context'] = $contextBlocks;
        }

        if ($module !== null) {
            $inputs['module'] = $module;

            if ($actionHint !== null) {
                $inputs['action'] = $actionHint;
            }
        }

        $requestPayload = [
            'company_id' => $companyId,
            'inputs' => $inputs,
        ];

        try {
            $response = $this->client->helpTool($requestPayload);
        } catch (AiServiceUnavailableException $exception) {
            throw new AiChatException('Workspace help service is unavailable. Please try again later.', null, 0, $exception);
        }

        if ($response['status'] !== 'success' || ! is_array($response['data'])) {
            throw new AiChatException($response['message'] ?? 'Failed to generate workspace help guide.', $response['errors'] ?? null);
        }

        $data = $response['data'];
        $cta = null;

        if ($module !== null) {
            try {
                $cta = $this->buildNavigationPayload([
                    'module' => $module,
                    'action' => $actionHint,
                    'entity_id' => $entityId,
                ]);
            } catch (AiChatException $exception) {
                report($exception);
                $cta = null;
            }
        }

        return [
            'summary' => is_string($data['summary'] ?? null) ? $data['summary'] : 'Workspace guide generated.',
            'payload' => is_array($data['payload'] ?? null) ? $data['payload'] : [],
            'citations' => $this->sanitizeArrayArgument($data['citations'] ?? []),
            'cta' => $cta,
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleNextBestAction(int $companyId, array $arguments): array
    {
        $userId = $this->coerceId($arguments['user_id'] ?? null);

        $user = $userId !== null
            ? User::query()->where('company_id', $companyId)->whereKey($userId)->first()
            : null;

        $contextEntity = $this->normalizeContextEntity($arguments);

        $recommendations = [];

        if ($contextEntity !== null) {
            $contextRecommendation = $this->buildContextEntityRecommendation($contextEntity);

            if ($contextRecommendation !== null) {
                $recommendations[] = $contextRecommendation;
            }
        }

        if ($pendingApprovals = $this->buildPendingApprovalRecommendation($companyId, $user)) {
            $recommendations[] = $pendingApprovals;
        }

        if ($overduePos = $this->buildOverduePurchaseOrderRecommendation($companyId)) {
            $recommendations[] = $overduePos;
        }

        if ($invoiceReview = $this->buildInvoiceReviewRecommendation($companyId)) {
            $recommendations[] = $invoiceReview;
        }

        if ($receivingBacklog = $this->buildReceivingBacklogRecommendation($companyId)) {
            $recommendations[] = $receivingBacklog;
        }

        $this->appendFallbackRecommendations($companyId, $recommendations);

        usort($recommendations, static function (array $a, array $b): int {
            $priorityA = (int) ($a['priority'] ?? 100);
            $priorityB = (int) ($b['priority'] ?? 100);

            return $priorityA <=> $priorityB;
        });

        $recommendations = array_map(static function (array $item): array {
            unset($item['priority']);

            return $item;
        }, array_slice($recommendations, 0, 5));

        return [
            'recommendations' => $recommendations,
            'meta' => [
                'generated_at' => Carbon::now()->toIso8601String(),
                'context_entity' => $contextEntity,
                'user_id' => $user?->id,
                'total' => count($recommendations),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleNavigate(int $companyId, array $arguments): array
    {
        return $this->buildNavigationPayload($arguments);
    }

    private function sanitizeLimit(mixed $value, int $default, int $max): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        $limit = (int) $value;

        if ($limit < 1) {
            $limit = $default;
        }

        return min($limit, $max);
    }

    private function sanitizeLocale(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim(str_replace('_', '-', $value)));

        if ($normalized === '') {
            return null;
        }

        return substr($normalized, 0, 10);
    }

    /**
     * @return list<string>
     */
    private function sanitizeStatuses(mixed $value): array
    {
        return $this->sanitizeStatusFilter($value, RFQ::STATUSES);
    }

    /**
     * @return list<string>
     */
    private function sanitizeQuoteStatuses(mixed $value): array
    {
        return $this->sanitizeStatusFilter($value, self::QUOTE_STATUSES);
    }

    /**
     * @return list<string>
     */
    private function sanitizePurchaseOrderStatuses(mixed $value): array
    {
        return $this->sanitizeStatusFilter($value, self::PURCHASE_ORDER_STATUSES);
    }

    /**
     * @return list<string>
     */
    private function sanitizeReceiptStatuses(mixed $value): array
    {
        return $this->sanitizeStatusFilter($value, self::RECEIPT_STATUSES);
    }

    /**
     * @return list<string>
     */
    private function sanitizeInvoiceStatuses(mixed $value): array
    {
        return $this->sanitizeStatusFilter($value, self::INVOICE_STATUSES);
    }

    /**
     * @return list<string>
     */
    private function sanitizePaymentStatuses(mixed $value): array
    {
        return $this->sanitizeStatusFilter($value, self::PAYMENT_STATUSES);
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function buildNavigationPayload(array $arguments): array
    {
        $moduleValue = $this->stringValue($arguments['module'] ?? $arguments['resource'] ?? $arguments['target'] ?? null);

        if ($moduleValue === null) {
            throw new AiChatException('workspace.navigate requires a module argument.');
        }

        $entityId = $this->stringValue(
            $arguments['entity_id']
            ?? $arguments['id']
            ?? $arguments['record_id']
            ?? $arguments['number']
            ?? null
        );

        $actionValue = $this->stringValue($arguments['action'] ?? $arguments['view'] ?? $arguments['intent'] ?? null);
        $moduleKey = $this->canonicalNavigationModule($moduleValue);

        if ($moduleKey === null) {
            throw new AiChatException(sprintf('Unsupported navigation module "%s" requested.', $moduleValue));
        }

        $action = $this->canonicalNavigationAction($actionValue, $entityId);
        $moduleConfig = self::NAVIGATION_MODULE_MAP[$moduleKey];
        $routeKey = $this->resolveNavigationRouteKey($moduleConfig, $action, $entityId);
        $routeConfig = $moduleConfig['routes'][$routeKey] ?? $moduleConfig['routes']['list'];

        $requiresEntity = array_key_exists('template', $routeConfig);

        if ($requiresEntity && $entityId === null) {
            throw new AiChatException('entity_id is required to build a detail navigation link.');
        }

        $encodedId = $entityId !== null ? rawurlencode($entityId) : null;
        $url = isset($routeConfig['template'])
            ? sprintf($routeConfig['template'], $encodedId)
            : (string) ($routeConfig['url'] ?? $moduleConfig['list_url']);

        $label = isset($routeConfig['label_template']) && $entityId !== null
            ? sprintf($routeConfig['label_template'], $entityId)
            : (string) ($routeConfig['label'] ?? $moduleConfig['label']);

        $breadcrumbs = $this->buildNavigationBreadcrumbs($moduleConfig, $routeKey, $label);

        return [
            'module' => $moduleKey,
            'action' => $routeKey,
            'entity_id' => $entityId,
            'url' => $url,
            'label' => $label,
            'breadcrumbs' => $breadcrumbs,
        ];
    }

    private function canonicalNavigationModule(string $value): ?string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        if (isset(self::NAVIGATION_MODULE_MAP[$normalized])) {
            return $normalized;
        }

        return self::NAVIGATION_MODULE_ALIASES[$normalized] ?? null;
    }

    private function canonicalNavigationAction(?string $value, ?string $entityId): string
    {
        if ($value === null) {
            return $entityId !== null ? 'detail' : 'list';
        }

        $normalized = strtolower(trim($value));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return self::NAVIGATION_ACTION_ALIASES[$normalized] ?? $normalized;
    }

    /**
     * @param array<string, mixed> $moduleConfig
     */
    private function resolveNavigationRouteKey(array $moduleConfig, string $action, ?string $entityId): string
    {
        if (isset($moduleConfig['routes'][$action])) {
            return $action;
        }

        if ($entityId !== null && isset($moduleConfig['routes']['detail'])) {
            return 'detail';
        }

        return isset($moduleConfig['routes']['list']) ? 'list' : array_key_first($moduleConfig['routes']);
    }

    /**
     * @param array<string, mixed> $moduleConfig
     * @return list<array{label:string,url:?string}>
     */
    private function buildNavigationBreadcrumbs(array $moduleConfig, string $routeKey, string $leafLabel): array
    {
        $breadcrumbs = [
            ['label' => 'Home', 'url' => '/app'],
        ];

        $listUrl = (string) ($moduleConfig['list_url'] ?? ($moduleConfig['routes']['list']['url'] ?? '/app'));

        $breadcrumbs[] = [
            'label' => $moduleConfig['label'],
            'url' => $routeKey === 'list' ? null : $listUrl,
        ];

        if ($routeKey !== 'list') {
            $breadcrumbs[] = [
                'label' => $moduleConfig['routes'][$routeKey]['breadcrumbs_label'] ?? $leafLabel,
                'url' => null,
            ];
        }

        return $breadcrumbs;
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{type:string,entity_id:string}|null
     */
    private function normalizeContextEntity(array $arguments): ?array
    {
        $context = $arguments['context_entity'] ?? null;

        if (! is_array($context)) {
            $context = [];
        }

        $type = $this->stringValue($context['type'] ?? $context['module'] ?? $arguments['entity_type'] ?? $arguments['module'] ?? null);
        $entityId = $this->stringValue($context['entity_id'] ?? $context['id'] ?? $arguments['entity_id'] ?? $arguments['record_id'] ?? null);

        if ($type === null || $entityId === null) {
            return null;
        }

        return [
            'type' => $type,
            'entity_id' => $entityId,
        ];
    }

    /**
     * @param array{type:string,entity_id:string} $contextEntity
     */
    private function buildContextEntityRecommendation(array $contextEntity): ?array
    {
        $module = $this->canonicalNavigationModule($contextEntity['type']);

        if ($module === null) {
            return null;
        }

        try {
            $link = $this->buildNavigationPayload([
                'module' => $module,
                'entity_id' => $contextEntity['entity_id'],
            ]);
        } catch (AiChatException $exception) {
            report($exception);

            return null;
        }

        return [
            'title' => sprintf('Continue with %s %s', strtoupper($module), $contextEntity['entity_id']),
            'reason' => 'You recently referenced this record. Jump back in to keep momentum.',
            'link' => $link,
            'priority' => 1,
        ];
    }

    private function buildPendingApprovalRecommendation(int $companyId, ?User $user): ?array
    {
        $query = AiApprovalRequest::query()
            ->forCompany($companyId)
            ->pending();

        if ($user !== null) {
            $query->where(static function (Builder $builder) use ($user): void {
                $builder->where('approver_user_id', $user->id);

                if ($user->role !== null) {
                    $builder->orWhere(static function (Builder $roleBuilder) use ($user): void {
                        $roleBuilder->whereNull('approver_user_id')
                            ->where('approver_role', $user->role);
                    });
                }
            });
        }

        $count = (clone $query)->count();

        if ($count === 0) {
            return null;
        }

        $sample = (clone $query)
            ->orderByDesc('created_at')
            ->first(['entity_type', 'entity_id', 'approver_role']);

        $reason = $count === 1
            ? 'One approval request needs attention.'
            : sprintf('%d approval requests need attention.', $count);

        if ($user !== null) {
            $reason = $count === 1
                ? 'You have one approval request waiting on you.'
                : sprintf('You have %d approval requests waiting on you.', $count);
        }

        $link = $this->buildNavigationPayload(['module' => 'po', 'action' => 'list']);

        return [
            'title' => 'Review pending approvals',
            'reason' => $reason,
            'link' => $link,
            'data' => [
                'count' => $count,
                'sample' => $sample ? [
                    'entity_type' => $sample->entity_type,
                    'entity_id' => $sample->entity_id,
                    'approver_role' => $sample->approver_role,
                ] : null,
            ],
            'priority' => 5,
        ];
    }

    private function buildOverduePurchaseOrderRecommendation(int $companyId): ?array
    {
        $query = PurchaseOrder::query()
            ->forCompany($companyId)
            ->whereIn('status', self::OPEN_PURCHASE_ORDER_STATUSES)
            ->whereNotNull('expected_at')
            ->where('expected_at', '<', Carbon::now()->startOfDay());

        $count = (clone $query)->count();

        if ($count === 0) {
            return null;
        }

        $sample = (clone $query)
            ->orderBy('expected_at')
            ->orderBy('id')
            ->first(['po_number', 'expected_at']);

        $reason = $count === 1
            ? 'One purchase order is past its expected receipt date.'
            : sprintf('%d purchase orders are past their expected receipt dates.', $count);

        $link = $this->buildNavigationPayload(['module' => 'po', 'action' => 'list']);

        return [
            'title' => 'Expedite overdue purchase orders',
            'reason' => $reason,
            'link' => $link,
            'data' => [
                'count' => $count,
                'sample' => $sample ? [
                    'po_number' => $sample->po_number,
                    'expected_at' => optional($sample->expected_at)->toIso8601String(),
                ] : null,
            ],
            'priority' => 10,
        ];
    }

    private function buildInvoiceReviewRecommendation(int $companyId): ?array
    {
        $statuses = [
            InvoiceStatus::BuyerReview->value,
            InvoiceStatus::Rejected->value,
        ];

        $query = Invoice::query()
            ->forCompany($companyId)
            ->whereIn('status', $statuses);

        $count = (clone $query)->count();

        if ($count === 0) {
            return null;
        }

        $sample = (clone $query)
            ->orderBy('due_date')
            ->orderBy('id')
            ->first(['invoice_number', 'due_date']);

        $reason = $count === 1
            ? 'One invoice is waiting for buyer review or resolution.'
            : sprintf('%d invoices are waiting for buyer review or resolution.', $count);

        $link = $this->buildNavigationPayload(['module' => 'invoice', 'action' => 'list']);

        return [
            'title' => 'Unblock invoices under review',
            'reason' => $reason,
            'link' => $link,
            'data' => [
                'count' => $count,
                'sample' => $sample ? [
                    'invoice_number' => $sample->invoice_number,
                    'due_date' => optional($sample->due_date)->toIso8601String(),
                ] : null,
            ],
            'priority' => 15,
        ];
    }

    private function buildReceivingBacklogRecommendation(int $companyId): ?array
    {
        $query = GoodsReceiptNote::query()
            ->forCompany($companyId)
            ->whereIn('status', ['pending', 'draft', 'inspecting']);

        $count = (clone $query)->count();

        if ($count === 0) {
            return null;
        }

        $sample = (clone $query)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first(['number', 'status']);

        $link = $this->buildNavigationPayload(['module' => 'receipt', 'action' => 'list']);

        $reason = $count === 1
            ? 'One receipt still needs to be logged or inspected.'
            : sprintf('%d receipts still need to be logged or inspected.', $count);

        return [
            'title' => 'Log outstanding receipts',
            'reason' => $reason,
            'link' => $link,
            'data' => [
                'count' => $count,
                'sample' => $sample ? [
                    'receipt_number' => $sample->number,
                    'status' => $sample->status,
                ] : null,
            ],
            'priority' => 20,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $recommendations
     */
    private function appendFallbackRecommendations(int $companyId, array &$recommendations): void
    {
        if (count($recommendations) >= 3) {
            return;
        }

        $snapshot = $this->handleProcurementSnapshot($companyId, ['limit' => 3]);

        foreach (self::NEXT_BEST_FALLBACKS as $key => $config) {
            if (count($recommendations) >= 3) {
                break;
            }

            $total = (int) ($snapshot[$key]['total_count'] ?? 0);

            if ($total < ($config['threshold'] ?? 1)) {
                continue;
            }

            try {
                $link = $this->buildNavigationPayload([
                    'module' => $config['module'],
                    'action' => $config['action'] ?? 'list',
                ]);
            } catch (AiChatException $exception) {
                report($exception);

                continue;
            }

            $recommendations[] = [
                'title' => $config['title'],
                'reason' => sprintf($config['reason_template'], $total),
                'link' => $link,
                'data' => ['count' => $total],
                'priority' => $config['priority'] ?? 100,
            ];
        }

        if (count($recommendations) >= 3) {
            return;
        }

        try {
            $recommendations[] = [
                'title' => 'Start a new RFQ',
                'reason' => 'Capture demand details and invite suppliers to quote.',
                'link' => $this->buildNavigationPayload(['module' => 'rfq', 'action' => 'create']),
                'priority' => 120,
            ];
        } catch (AiChatException $exception) {
            report($exception);
        }
    }

    /**
     * @return list<string>
     */
    private function sanitizeDisputeStatuses(mixed $value): array
    {
        return $this->sanitizeLooseStatuses($value);
    }

    /**
     * @return list<string>
     */
    private function sanitizeLooseStatuses(mixed $value): array
    {
        if (is_string($value)) {
            $candidates = array_map('trim', explode(',', $value));
        } elseif (is_array($value)) {
            $candidates = array_map(static fn ($entry) => is_string($entry) ? trim($entry) : '', $value);
        } else {
            $candidates = [];
        }

        $sanitized = [];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $normalized = strtolower($candidate);

            if (! in_array($normalized, $sanitized, true)) {
                $sanitized[] = $normalized;
            }
        }

        return $sanitized;
    }

    /**
     * @param list<string> $allowed
     * @return list<string>
     */
    private function sanitizeStatusFilter(mixed $value, array $allowed): array
    {
        if (is_string($value)) {
            $candidates = array_map('trim', explode(',', $value));
        } elseif (is_array($value)) {
            $candidates = array_map(static fn ($entry) => is_string($entry) ? trim($entry) : '', $value);
        } else {
            $candidates = [];
        }

        $allowedNormalized = array_map('strtolower', $allowed);
        $sanitized = [];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $normalized = strtolower($candidate);

            if (! in_array($normalized, $allowedNormalized, true)) {
                continue;
            }

            if (! in_array($normalized, $sanitized, true)) {
                $sanitized[] = $normalized;
            }
        }

        return $sanitized;
    }

    /**
     * @param Builder $query
     * @param list<string>|null $allowedStatuses
     * @return array<string, int>
     */
    private function summarizeStatusCounts(Builder $query, ?array $allowedStatuses = null): array
    {
        $counts = (clone $query)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn ($count) => (int) $count)
            ->all();

        if ($allowedStatuses !== null) {
            return $this->normalizeStatusCounts($counts, $allowedStatuses);
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param array<string, int> $counts
     * @param list<string> $allowed
     * @return array<string, int>
     */
    private function normalizeStatusCounts(array $counts, array $allowed): array
    {
        $normalized = [];

        foreach ($allowed as $status) {
            $normalized[$status] = (int) ($counts[$status] ?? 0);
        }

        foreach ($counts as $status => $value) {
            if (! array_key_exists($status, $normalized)) {
                $normalized[$status] = (int) $value;
            }
        }

        return $normalized;
    }

    private function applyItemStatusFilter(Builder $query, array $statuses): void
    {
        if ($statuses === [] || count($statuses) >= count(self::ITEM_STATUSES)) {
            return;
        }

        $query->where(function (Builder $builder) use ($statuses): void {
            $applied = false;

            if (in_array('active', $statuses, true)) {
                $builder->where('active', true);
                $applied = true;
            }

            if (in_array('inactive', $statuses, true)) {
                $clause = static function (Builder $sub): void {
                    $sub->where('active', false)
                        ->orWhereNull('active');
                };

                if ($applied) {
                    $builder->orWhere($clause);
                } else {
                    $builder->where($clause);
                }
            }
        });
    }

    private function summarizeItemStatusCounts(Builder $query): array
    {
        $counts = (clone $query)
            ->selectRaw("CASE WHEN COALESCE(active, 0) = 1 THEN 'active' ELSE 'inactive' END as status, COUNT(*) as aggregate")
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn ($count) => (int) $count)
            ->all();

        return $this->normalizeStatusCounts($counts, self::ITEM_STATUSES);
    }

    private function formatApprovalRequest(AiApprovalRequest $request): array
    {
        $request->loadMissing([
            'approverUser' => static fn ($query) => $query->select(['id', 'name']),
            'requestedByUser' => static fn ($query) => $query->select(['id', 'name']),
        ]);

        return [
            'request_id' => $request->id,
            'workflow_id' => $request->workflow_id,
            'workflow_step_id' => $request->workflow_step_id,
            'step_index' => $request->step_index,
            'step_type' => $request->step_type,
            'entity_type' => $request->entity_type,
            'entity_id' => $request->entity_id,
            'approver_role' => $request->approver_role,
            'approver_user' => $request->approverUser ? [
                'id' => $request->approverUser->id,
                'name' => $request->approverUser->name,
            ] : null,
            'requested_by' => $request->requestedByUser ? [
                'id' => $request->requestedByUser->id,
                'name' => $request->requestedByUser->name,
            ] : ($request->requested_by ? ['id' => $request->requested_by, 'name' => null] : null),
            'status' => $request->status,
            'message' => $request->message,
            'created_at' => $this->isoDateTime($request->created_at),
            'resolved_at' => $this->isoDateTime($request->resolved_at),
        ];
    }

    private function locateWorkflowStep(int $companyId, string $workflowId, ?int $stepIndex, ?string $stepType): ?AiWorkflowStep
    {
        if ($stepIndex !== null) {
            $step = AiWorkflowStep::query()
                ->forCompany($companyId)
                ->where('workflow_id', $workflowId)
                ->where('step_index', $stepIndex)
                ->first(['id', 'workflow_id', 'step_index', 'action_type']);

            if ($step instanceof AiWorkflowStep) {
                return $step;
            }
        }

        if ($stepType !== null) {
            return AiWorkflowStep::query()
                ->forCompany($companyId)
                ->where('workflow_id', $workflowId)
                ->where('action_type', $stepType)
                ->orderByDesc('step_index')
                ->first(['id', 'workflow_id', 'step_index', 'action_type']);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizeArrayArgument(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @return list<string>
     */
    private function sanitizeStringArray(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $candidates = is_array($value) ? $value : [$value];
        $sanitized = [];

        foreach ($candidates as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            $trimmed = trim($entry);

            if ($trimmed === '' || in_array($trimmed, $sanitized, true)) {
                continue;
            }

            $sanitized[] = $trimmed;
        }

        return $sanitized;
    }

    /**
     * @param array<string, mixed>|null $capabilities
     * @return array<string, list<string>>
     */
    private function capabilityHighlights(?array $capabilities): array
    {
        if ($capabilities === null) {
            return [];
        }

        $keys = ['methods', 'materials', 'finishes', 'tolerances', 'industries'];
        $highlights = [];

        foreach ($keys as $key) {
            $values = $capabilities[$key] ?? null;

            if (! is_array($values) || $values === []) {
                continue;
            }

            $highlights[$key] = array_values(array_slice(array_map(static fn ($entry) => (string) $entry, $values), 0, 3));
        }

        return $highlights;
    }

    private function normalizeRateToPercentage(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $numeric = (float) $value;

        if ($numeric <= 1 && $numeric >= 0) {
            $numeric *= 100;
        }

        return round($numeric, 2);
    }

    /**
     * @return array{percentage: ?float, sample: int}
     */
    private function calculateReceiptOnTimeRate(int $companyId, int $supplierId): array
    {
        $receipts = GoodsReceiptNote::query()
            ->forCompany($companyId)
            ->whereHas('purchaseOrder', static function (Builder $builder) use ($supplierId): void {
                $builder->where('supplier_id', $supplierId);
            })
            ->with([
                'purchaseOrder' => static fn ($builder) => $builder->select(['id', 'company_id', 'supplier_id', 'expected_at']),
            ])
            ->orderByDesc('inspected_at')
            ->limit(250)
            ->get(['id', 'purchase_order_id', 'inspected_at', 'created_at']);

        $sample = 0;
        $onTime = 0;

        foreach ($receipts as $receipt) {
            $expectedAt = $receipt->purchaseOrder?->expected_at;

            if ($expectedAt === null) {
                continue;
            }

            $receivedAt = $receipt->inspected_at ?? $receipt->created_at;

            if ($receivedAt === null) {
                continue;
            }

            ++$sample;

            $received = Carbon::parse($receivedAt);
            $expected = Carbon::parse($expectedAt);

            if ($received->lessThanOrEqualTo($expected)) {
                ++$onTime;
            }
        }

        if ($sample === 0) {
            return ['percentage' => null, 'sample' => 0];
        }

        return ['percentage' => round(($onTime / $sample) * 100, 2), 'sample' => $sample];
    }

    /**
     * @return array{percentage: ?float, sample: int}
     */
    private function calculateReceiptDefectRate(int $companyId, int $supplierId): array
    {
        $aggregates = GoodsReceiptLine::query()
            ->selectRaw('COUNT(*) as lines_count, SUM(COALESCE(received_qty, 0)) as received_total, SUM(COALESCE(rejected_qty, 0)) as rejected_total')
            ->whereHas('goodsReceiptNote', static function (Builder $builder) use ($companyId, $supplierId): void {
                $builder->forCompany($companyId)
                    ->whereHas('purchaseOrder', static fn ($poQuery) => $poQuery->where('supplier_id', $supplierId));
            })
            ->first();

        if ($aggregates === null || (int) ($aggregates->lines_count ?? 0) === 0) {
            return ['percentage' => null, 'sample' => 0];
        }

        $receivedTotal = (float) ($aggregates->received_total ?? 0.0);
        $rejectedTotal = (float) ($aggregates->rejected_total ?? 0.0);

        if ($receivedTotal <= 0) {
            return ['percentage' => null, 'sample' => (int) $aggregates->lines_count];
        }

        $percentage = round(($rejectedTotal / $receivedTotal) * 100, 2);

        return ['percentage' => $percentage, 'sample' => (int) $aggregates->lines_count];
    }

    /**
     * @return array{percentage: ?float, sample: int}
     */
    private function calculateDisputeRate(int $companyId, int $supplierId): array
    {
        $totalInvoices = Invoice::query()
            ->forCompany($companyId)
            ->where('supplier_id', $supplierId)
            ->count();

        if ($totalInvoices === 0) {
            return ['percentage' => null, 'sample' => 0];
        }

        $disputedInvoices = Invoice::query()
            ->forCompany($companyId)
            ->where('supplier_id', $supplierId)
            ->where(static function (Builder $builder): void {
                $builder->where('status', InvoiceStatus::Rejected->value)
                    ->orWhereRaw("LOWER(COALESCE(matched_status, '')) = ?", ['disputed'])
                    ->orWhereRaw("LOWER(COALESCE(matched_status, '')) = ?", ['hold'])
                    ->orWhereRaw("LOWER(COALESCE(matched_status, '')) = ?", ['exception']);
            })
            ->count();

        if ($disputedInvoices === 0) {
            return ['percentage' => 0.0, 'sample' => $totalInvoices];
        }

        return ['percentage' => round(($disputedInvoices / $totalInvoices) * 100, 2), 'sample' => $totalInvoices];
    }

    private function computeSupplierRiskScore(?float $onTimePct, ?float $defectPct, ?float $disputePct): float
    {
        $components = [];

        if ($onTimePct !== null) {
            $components[] = ['weight' => 0.4, 'value' => max(0.0, min(100.0, $onTimePct)) / 100];
        }

        if ($defectPct !== null) {
            $components[] = ['weight' => 0.3, 'value' => 1 - max(0.0, min(100.0, $defectPct)) / 100];
        }

        if ($disputePct !== null) {
            $components[] = ['weight' => 0.3, 'value' => 1 - max(0.0, min(100.0, $disputePct)) / 100];
        }

        if ($components === []) {
            return 50.0;
        }

        $weighted = 0.0;
        $weightTotal = 0.0;

        foreach ($components as $component) {
            $weighted += $component['value'] * $component['weight'];
            $weightTotal += $component['weight'];
        }

        if ($weightTotal <= 0) {
            return 50.0;
        }

        return round(($weighted / $weightTotal) * 100, 2);
    }

    private function countOpenPurchaseOrders(int $companyId, int $supplierId): int
    {
        return PurchaseOrder::query()
            ->forCompany($companyId)
            ->where('supplier_id', $supplierId)
            ->where(static function (Builder $builder): void {
                $builder->whereIn('status', self::OPEN_PURCHASE_ORDER_STATUSES)
                    ->orWhereNull('status');
            })
            ->count();
    }

    private function parseDateValue(mixed $value): ?Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::createFromInterface($value);
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                return null;
            }

            try {
                return Carbon::parse($trimmed);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function sanitizeContextBlocks(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $blocks = [];

        foreach ($value as $block) {
            if (! is_array($block)) {
                continue;
            }

            $blocks[] = $block;

            if (count($blocks) >= 5) {
                break;
            }
        }

        return $blocks;
    }

    private function stringValue(mixed $value): ?string
    {
        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    private function currencyMinorUnit(string $currency): int
    {
        static $cache = [];

        $code = strtoupper($currency);

        if (isset($cache[$code])) {
            return $cache[$code];
        }

        $minor = Currency::query()
            ->where('code', $code)
            ->value('minor_unit');

        $cache[$code] = $minor !== null ? (int) $minor : 2;

        return $cache[$code];
    }

    private function coerceId(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
    }

    private function coerceStepIndex(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $intValue = (int) $value;

        return $intValue >= 0 ? $intValue : null;
    }

    private function formatMoney(null|string $decimalValue, ?int $minorValue, string $currency): ?float
    {
        if ($decimalValue !== null && $decimalValue !== '') {
            return (float) $decimalValue;
        }

        if ($minorValue === null) {
            return null;
        }

        $minorUnit = $this->currencyMinorUnit($currency);
        $precision = 10 ** $minorUnit;

        return round($minorValue / $precision, $minorUnit);
    }

    private function decimalToMinor(null|string $value, string $currency): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $minorUnit = $this->currencyMinorUnit($currency);
        $precision = 10 ** $minorUnit;

        return (int) round(((float) $value) * $precision);
    }

    /**
     * @return array{inventory: array{on_hand: float, allocated: float, on_order: float}, settings: array<string, mixed>|null}
     */
    private function resolveInventorySummary(int $companyId, int $partId): array
    {
        $inventoryQuery = Inventory::query()
            ->forCompany($companyId)
            ->where('part_id', $partId);

        $onHand = (float) (clone $inventoryQuery)->sum('on_hand');
        $allocated = (float) (clone $inventoryQuery)->sum('allocated');
        $onOrder = (float) (clone $inventoryQuery)->sum('on_order');

        $setting = InventorySetting::query()
            ->forCompany($companyId)
            ->where('part_id', $partId)
            ->first(['min_qty', 'max_qty', 'safety_stock', 'reorder_qty']);

        return [
            'inventory' => [
                'on_hand' => $onHand,
                'allocated' => $allocated,
                'on_order' => $onOrder,
            ],
            'settings' => $setting ? [
                'min_qty' => $setting->min_qty,
                'max_qty' => $setting->max_qty,
                'safety_stock' => $setting->safety_stock,
                'reorder_qty' => $setting->reorder_qty,
            ] : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolvePreferredSuppliers(int $companyId, Part $part): array
    {
        $stored = PartPreferredSupplier::query()
            ->forCompany($companyId)
            ->where('part_id', $part->getKey())
            ->orderBy('priority')
            ->limit(5)
            ->get(['id', 'supplier_id', 'supplier_name', 'priority', 'notes']);

        if ($stored->isNotEmpty()) {
            $supplierIds = $stored->pluck('supplier_id')
                ->filter(static fn ($value) => $value !== null)
                ->map(static fn ($value) => (int) $value)
                ->unique()
                ->all();

            $suppliers = $supplierIds === []
                ? collect()
                : Supplier::query()
                    ->forCompany($companyId)
                    ->withTrashed()
                    ->whereIn('id', $supplierIds)
                    ->get(['id', 'name', 'city', 'country'])
                    ->keyBy('id');

            return $stored->map(function (PartPreferredSupplier $preference) use ($suppliers): array {
                $supplier = $preference->supplier_id ? $suppliers->get($preference->supplier_id) : null;

                return [
                    'supplier_id' => $preference->supplier_id,
                    'name' => $supplier?->name ?? $preference->supplier_name,
                    'location' => $supplier ? $this->formatLocation($supplier->city ?? null, $supplier->country ?? null) : null,
                    'priority' => (int) $preference->priority,
                    'notes' => $preference->notes,
                    'awards_count' => null,
                    'last_awarded_at' => null,
                ];
            })->values()->all();
        }

        $partNumber = $this->stringValue($part->part_number ?? null);

        if ($partNumber === null) {
            return [];
        }

        $awardStats = RfqItemAward::query()
            ->forCompany($companyId)
            ->selectRaw('supplier_id, COUNT(*) as awards_count, MAX(awarded_at) as last_awarded_at')
            ->whereNotNull('supplier_id')
            ->whereHas('rfqItem', static function (Builder $builder) use ($companyId, $partNumber): void {
                $builder->forCompany($companyId)
                    ->where('part_number', $partNumber);
            })
            ->groupBy('supplier_id')
            ->orderByDesc('awards_count')
            ->orderByDesc('last_awarded_at')
            ->limit(5)
            ->get();

        if ($awardStats->isEmpty()) {
            return [];
        }

        $supplierIds = $awardStats
            ->pluck('supplier_id')
            ->filter(static fn ($id) => $id !== null)
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->all();

        if ($supplierIds === []) {
            return [];
        }

        $suppliers = Supplier::query()
            ->forCompany($companyId)
            ->withTrashed()
            ->whereIn('id', $supplierIds)
            ->get(['id', 'name', 'city', 'country'])
            ->keyBy('id');

        return $awardStats
            ->map(function ($stat, int $index) use ($suppliers): array {
                $supplierId = (int) $stat->supplier_id;
                $supplier = $suppliers->get($supplierId);

                return [
                    'supplier_id' => $supplierId,
                    'name' => $supplier?->name,
                    'location' => $supplier ? $this->formatLocation($supplier->city ?? null, $supplier->country ?? null) : null,
                    'awards_count' => (int) ($stat->awards_count ?? 0),
                    'last_awarded_at' => $this->isoDateTime($stat->last_awarded_at ?? null),
                    'priority' => $index + 1,
                    'notes' => null,
                ];
            })
            ->filter(static fn (array $entry): bool => $entry['supplier_id'] > 0)
            ->values()
            ->all();
    }

    private function resolveLastPurchase(int $companyId, Part $part): ?array
    {
        $partNumber = $this->stringValue($part->part_number ?? null);

        if ($partNumber === null) {
            return null;
        }

        $line = PurchaseOrderLine::query()
            ->select([
                'po_lines.purchase_order_id',
                'po_lines.quantity',
                'po_lines.uom',
                'po_lines.unit_price',
                'po_lines.unit_price_minor',
                'po_lines.currency',
                'purchase_orders.po_number',
                'purchase_orders.ordered_at',
                'purchase_orders.created_at as po_created_at',
                'purchase_orders.currency as po_currency',
                'purchase_orders.supplier_id',
                'suppliers.name as supplier_name',
                'suppliers.city as supplier_city',
                'suppliers.country as supplier_country',
            ])
            ->join('purchase_orders', static function ($join) use ($companyId): void {
                $join->on('purchase_orders.id', '=', 'po_lines.purchase_order_id')
                    ->where('purchase_orders.company_id', '=', $companyId);
            })
            ->leftJoin('suppliers', static function ($join) use ($companyId): void {
                $join->on('suppliers.id', '=', 'purchase_orders.supplier_id')
                    ->where(function ($condition) use ($companyId): void {
                        $condition->where('suppliers.company_id', '=', $companyId)
                            ->orWhereNull('suppliers.id');
                    });
            })
            ->join('rfq_items', static function ($join) use ($companyId, $partNumber): void {
                $join->on('rfq_items.id', '=', 'po_lines.rfq_item_id')
                    ->where('rfq_items.company_id', '=', $companyId)
                    ->where('rfq_items.part_number', '=', $partNumber);
            })
            ->orderByDesc(DB::raw('COALESCE(purchase_orders.ordered_at, purchase_orders.created_at)'))
            ->orderByDesc('po_lines.id')
            ->first();

        if ($line === null) {
            return null;
        }

        $currency = strtoupper($line->currency ?? $line->po_currency ?? 'USD');
        $unitPrice = $this->formatMoney($line->unit_price, $line->unit_price_minor, $currency);

        $supplier = null;

        if ($line->supplier_id !== null) {
            $supplier = [
                'supplier_id' => (int) $line->supplier_id,
                'name' => $line->supplier_name,
                'location' => $this->formatLocation($line->supplier_city ?? null, $line->supplier_country ?? null),
            ];
        }

        return [
            'po_id' => (int) $line->purchase_order_id,
            'po_number' => $line->po_number,
            'ordered_at' => $this->isoDateTime($line->ordered_at ?? $line->po_created_at),
            'quantity' => (int) $line->quantity,
            'uom' => $line->uom,
            'currency' => $currency,
            'unit_price' => $unitPrice,
            'supplier' => $supplier,
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveInvoiceExceptions(Invoice $invoice): array
    {
        $flags = [];

        if ($invoice->purchase_order_id === null) {
            $flags[] = 'missing_po';
        }

        $matchedStatus = $this->stringValue($invoice->matched_status ?? null);

        if ($matchedStatus !== null) {
            $normalized = strtolower($matchedStatus);

            if ($normalized !== '' && $normalized !== 'matched' && ! in_array($normalized, $flags, true)) {
                $flags[] = $normalized;
            }
        }

        if ($invoice->relationLoaded('matches')) {
            foreach ($invoice->matches as $match) {
                $result = $this->stringValue($match->result ?? null);

                if ($result === null) {
                    continue;
                }

                $normalized = strtolower($result);

                if ($normalized === '' || $normalized === 'matched' || in_array($normalized, $flags, true)) {
                    continue;
                }

                $flags[] = $normalized;
            }
        }

        return $flags;
    }

    /**
     * @return array<string, int>
     */
    private function buildInvoiceMatchSummary(Invoice $invoice): array
    {
        if (! $invoice->relationLoaded('matches')) {
            return [];
        }

        $summary = [];

        foreach ($invoice->matches as $match) {
            $key = $this->stringValue($match->result ?? null);

            if ($key === null) {
                continue;
            }

            $normalized = strtolower($key);

            if ($normalized === '') {
                continue;
            }

            $summary[$normalized] = ($summary[$normalized] ?? 0) + 1;
        }

        ksort($summary);

        return $summary;
    }

    private function resolvePaymentStatus(mixed $paidAt): string
    {
        if ($paidAt instanceof DateTimeInterface) {
            return 'paid';
        }

        if (is_string($paidAt) && trim($paidAt) !== '') {
            return 'paid';
        }

        return 'pending';
    }

    /**
     * @return array<string, mixed>
     */
    private function formatContractPayload(Document $document, bool $includeDownloadUrl = false): array
    {
        $meta = is_array($document->meta) ? $document->meta : [];
        $contractNumber = $this->stringValue($meta['contract_number'] ?? null)
            ?? ($document->filename ?? ('CON-' . $document->id));
        $startSource = $meta['start_date'] ?? ($meta['effective_date'] ?? $document->created_at);
        $endSource = $meta['end_date'] ?? $document->expires_at;
        $status = $this->stringValue($meta['status'] ?? null);

        if ($status === null && $document->expires_at instanceof DateTimeInterface) {
            $status = $document->expires_at->isPast() ? 'expired' : 'active';
        }

        $documentData = [
            'document_id' => $document->id,
            'filename' => $document->filename,
            'mime' => $document->mime,
            'size_bytes' => $document->size_bytes,
            'updated_at' => $this->isoDateTime($document->updated_at),
        ];

        if ($includeDownloadUrl) {
            $documentData['download_url'] = $document->temporaryDownloadUrl(15);
        }

        return [
            'contract_id' => $document->id,
            'contract_number' => $contractNumber,
            'status' => $status,
            'start_date' => $this->isoDateTime($startSource),
            'end_date' => $this->isoDateTime($endSource),
            'supplier' => $this->extractContractSupplier($document),
            'key_terms' => $meta['key_terms'] ?? ($meta['summary'] ?? null),
            'document' => $documentData,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractContractSupplier(Document $document): ?array
    {
        $entity = $document->documentable;

        if ($entity instanceof Supplier) {
            return [
                'supplier_id' => $entity->id,
                'name' => $entity->name,
                'location' => $this->formatLocation($entity->city ?? null, $entity->country ?? null),
            ];
        }

        if ($entity instanceof PurchaseOrder) {
            $supplier = $entity->supplier;

            if ($supplier instanceof Supplier) {
                return [
                    'supplier_id' => $supplier->id,
                    'name' => $supplier->name,
                    'location' => $this->formatLocation($supplier->city ?? null, $supplier->country ?? null),
                    'po_number' => $entity->po_number,
                ];
            }
        }

        $meta = is_array($document->meta) ? $document->meta : [];
        $supplierMeta = $meta['supplier'] ?? null;

        if (is_array($supplierMeta)) {
            return [
                'supplier_id' => $supplierMeta['supplier_id'] ?? null,
                'name' => $supplierMeta['name'] ?? null,
                'location' => $supplierMeta['location'] ?? null,
            ];
        }

        return null;
    }

    private function formatLocation(?string $city, ?string $country): ?string
    {
        $city = $city !== null ? trim($city) : '';
        $country = $country !== null ? trim($country) : '';
        $combined = trim($city . ', ' . $country, ', ');

        return $combined === '' ? null : $combined;
    }

    private function isoDateTime(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::createFromInterface($value)->toIso8601String();
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value)->toIso8601String();
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }
}
