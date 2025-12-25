<?php

namespace App\Services\Ai;

use App\Enums\AiChatToolCall;
use App\Exceptions\AiChatException;
use App\Exceptions\AiServiceUnavailableException;
use App\Enums\InvoiceStatus;
use App\Models\Currency;
use App\Models\Inventory;
use App\Models\InventorySetting;
use App\Models\Invoice;
use App\Models\Part;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\RfqItemAward;
use App\Models\Supplier;
use App\Services\Ai\AiClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class WorkspaceToolResolver
{
    public const RESOLVER_METHODS = [
        AiChatToolCall::SearchRfqs->value => 'handleSearchRfqs',
        AiChatToolCall::GetRfq->value => 'handleGetRfq',
        AiChatToolCall::ListSuppliers->value => 'handleListSuppliers',
        AiChatToolCall::GetQuotesForRfq->value => 'handleGetQuotesForRfq',
        AiChatToolCall::GetInventoryItem->value => 'handleGetInventoryItem',
        AiChatToolCall::LowStock->value => 'handleLowStock',
        AiChatToolCall::GetAwards->value => 'handleGetAwards',
        AiChatToolCall::QuoteStats->value => 'handleQuoteStats',
        AiChatToolCall::GetReceipts->value => 'handleGetReceipts',
        AiChatToolCall::GetInvoices->value => 'handleGetInvoices',
        AiChatToolCall::ApproveInvoice->value => 'handleApproveInvoice',
        AiChatToolCall::Help->value => 'handleHelp',
    ];

    public const MAX_TOOL_CALLS = 5;

    public function __construct(private readonly AiClient $client)
    {
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

        $inventoryQuery = Inventory::query()
            ->forCompany($companyId)
            ->where('part_id', $part->id);

        $onHand = (float) (clone $inventoryQuery)->sum('on_hand');
        $allocated = (float) (clone $inventoryQuery)->sum('allocated');
        $onOrder = (float) (clone $inventoryQuery)->sum('on_order');

        $setting = InventorySetting::query()
            ->forCompany($companyId)
            ->where('part_id', $part->id)
            ->first(['min_qty', 'max_qty', 'safety_stock', 'reorder_qty']);

        return [
            'item' => [
                'part_id' => $part->id,
                'part_number' => $part->part_number,
                'name' => $part->name,
                'uom' => $part->uom,
                'category' => $part->category,
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
    private function handleGetReceipts(int $companyId, array $arguments): array
    {
        $limit = $this->sanitizeLimit($arguments['limit'] ?? null, 3, 10);
        $context = $this->sanitizeArrayArgument($arguments['context'] ?? null);
        $filters = $this->sanitizeArrayArgument($arguments['filters'] ?? null);

        $items = [];

        for ($index = 1; $index <= $limit; $index++) {
            $items[] = [
                'id' => $index,
                'receipt_number' => sprintf('RCPT-%04d', $index),
                'supplier_name' => $filters['supplier_name'] ?? 'Placeholder Supplier',
                'status' => 'received',
                'total_amount' => 1250.0 + ($index * 15),
                'created_at' => now()->subDays($index)->toIso8601String(),
            ];
        }

        return [
            'items' => $items,
            'meta' => [
                'limit' => $limit,
                'context' => $context,
                'filters' => $filters,
                'note' => 'Placeholder receiving data; replace with real receiving + QC integration.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function handleGetInvoices(int $companyId, array $arguments): array
    {
        $limit = $this->sanitizeLimit($arguments['limit'] ?? null, 3, 10);
        $context = $this->sanitizeArrayArgument($arguments['context'] ?? null);
        $filters = $this->sanitizeArrayArgument($arguments['filters'] ?? null);

        $items = [];

        for ($index = 1; $index <= $limit; $index++) {
            $items[] = [
                'id' => $index,
                'invoice_number' => sprintf('INV-%04d', $index),
                'supplier_name' => $filters['supplier_name'] ?? 'Placeholder Supplier',
                'status' => 'pending',
                'total_amount' => 2450.0 + ($index * 25),
                'created_at' => now()->subDays($index + 2)->toIso8601String(),
            ];
        }

        return [
            'items' => $items,
            'meta' => [
                'limit' => $limit,
                'context' => $context,
                'filters' => $filters,
                'note' => 'Placeholder invoice data; replace with AP + 3-way match pipeline.',
            ],
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
    private function handleHelp(int $companyId, array $arguments): array
    {
        $topic = $this->stringValue($arguments['topic'] ?? null)
            ?? $this->stringValue($arguments['query'] ?? null)
            ?? $this->stringValue($arguments['action'] ?? null)
            ?? 'copilot workspace basics';

        $inputs = ['topic' => $topic];

        foreach (['query', 'action'] as $field) {
            $value = $this->stringValue($arguments[$field] ?? null);
            if ($value !== null) {
                $inputs[$field] = $value;
            }
        }

        $locale = $this->stringValue($arguments['locale'] ?? null);
        if ($locale !== null) {
            $sanitizedLocale = $this->sanitizeLocale($locale);
            if ($sanitizedLocale !== null) {
                $inputs['locale'] = $sanitizedLocale;
            }
        }

        $requestPayload = [
            'company_id' => $companyId,
            'context' => $this->sanitizeContextBlocks($arguments['context'] ?? null),
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

        return [
            'summary' => is_string($data['summary'] ?? null) ? $data['summary'] : 'Workspace guide generated.',
            'payload' => is_array($data['payload'] ?? null) ? $data['payload'] : [],
            'citations' => $this->sanitizeArrayArgument($data['citations'] ?? []),
        ];
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
     * @return array<string, mixed>
     */
    private function sanitizeArrayArgument(mixed $value): array
    {
        return is_array($value) ? $value : [];
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
}
