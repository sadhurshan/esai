<?php

namespace App\Services\Ai\Converters;

use App\Models\AiActionDraft;
use App\Models\GoodsReceiptNote;
use App\Models\Invoice;
use App\Models\InvoiceMatch;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceMatchConverter extends AbstractDraftConverter
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly ValidationFactory $validator,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array{entity: Invoice}
     */
    public function convert(AiActionDraft $draft, User $user): array
    {
        $result = $this->extractOutputAndPayload($draft, AiActionDraft::TYPE_INVOICE_MATCH);
        $payload = $result['payload'];
        $validated = $this->validatePayload($payload);

        $invoice = $this->resolveInvoice($draft, $validated['invoice_id'], $user->company_id);
        $companyId = (int) $invoice->company_id;

        if ($companyId <= 0) {
            throw $this->validationError('invoice_id', 'Invoice is missing a company assignment.');
        }

        if ($user->company_id !== null && (int) $user->company_id !== $companyId) {
            throw $this->validationError('invoice_id', 'Invoice does not belong to your company.');
        }

        $purchaseOrder = $this->resolvePurchaseOrder($invoice, $validated['matched_po_id'], $companyId);
        $receiptNotes = $this->resolveReceiptNotes($validated['matched_receipt_ids'], $companyId, $purchaseOrder?->id ?? $invoice->purchase_order_id);

        $recommendationStatus = $validated['recommendation']['status'];

        $invoice = $this->db->transaction(function () use ($invoice, $companyId, $purchaseOrder, $receiptNotes, $validated, $recommendationStatus): Invoice {
            InvoiceMatch::query()->where('invoice_id', $invoice->id)->delete();

            $summaryDetails = $this->buildSummaryDetails($validated, $receiptNotes);
            $purchaseOrderId = $purchaseOrder?->id ?? $invoice->purchase_order_id;
            $primaryReceiptId = $receiptNotes->first()?->id;

            if ($validated['mismatches'] === []) {
                InvoiceMatch::create([
                    'company_id' => $companyId,
                    'invoice_id' => $invoice->id,
                    'purchase_order_id' => $purchaseOrderId,
                    'goods_receipt_note_id' => $primaryReceiptId,
                    'result' => 'matched',
                    'details' => $summaryDetails,
                ]);
            } else {
                foreach ($validated['mismatches'] as $mismatch) {
                    $details = $summaryDetails;
                    $details['mismatch'] = array_filter([
                        'type' => $mismatch['type'],
                        'line_reference' => $mismatch['line_reference'],
                        'severity' => $mismatch['severity'],
                        'detail' => $mismatch['detail'],
                        'expected' => $mismatch['expected'],
                        'actual' => $mismatch['actual'],
                    ], static fn ($value) => $value !== null && $value !== '');

                    InvoiceMatch::create([
                        'company_id' => $companyId,
                        'invoice_id' => $invoice->id,
                        'purchase_order_id' => $purchaseOrderId,
                        'goods_receipt_note_id' => $primaryReceiptId,
                        'result' => $this->mapResultFromMismatch($mismatch['type']),
                        'details' => $details,
                    ]);
                }
            }

            $invoice->matched_status = $recommendationStatus === 'approve' ? 'matched' : 'hold';
            $invoice->save();

            return $invoice->fresh(['matches']);
        });

        $this->auditLogger->custom($invoice, 'invoice_match_reviewed', [
            'source' => 'copilot',
            'invoice_id' => $invoice->id,
            'purchase_order_id' => $invoice->purchase_order_id,
            'recommendation' => $validated['recommendation']['status'],
            'mismatch_count' => count($validated['mismatches']),
        ]);

        $draft->forceFill([
            'entity_type' => $invoice->getMorphClass(),
            'entity_id' => $invoice->id,
        ])->save();

        return ['entity' => $invoice];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *     invoice_id: string,
     *     matched_po_id: ?string,
     *     matched_receipt_ids: list<string>,
     *     match_score: ?float,
     *     mismatches: list<array{
     *         type: string,
     *         line_reference: ?string,
     *         severity: ?string,
     *         detail: string,
     *         expected: mixed,
     *         actual: mixed
     *     }>,
     *     recommendation: array{status: string, explanation: string},
     *     analysis_notes: list<string>
     * }
     */
    private function validatePayload(array $payload): array
    {
        $validator = $this->validator->make(
            [
                'invoice_id' => $payload['invoice_id'] ?? null,
                'matched_po_id' => $payload['matched_po_id'] ?? null,
                'matched_receipt_ids' => $payload['matched_receipt_ids'] ?? null,
                'match_score' => $payload['match_score'] ?? null,
                'mismatches' => $payload['mismatches'] ?? null,
                'recommendation' => $payload['recommendation'] ?? null,
                'analysis_notes' => $payload['analysis_notes'] ?? null,
            ],
            [
                'invoice_id' => ['required', 'string', 'max:120'],
                'matched_po_id' => ['nullable', 'string', 'max:120'],
                'matched_receipt_ids' => ['nullable', 'array', 'max:25'],
                'matched_receipt_ids.*' => ['string', 'max:120'],
                'match_score' => ['nullable', 'numeric', 'between:0,1'],
                'mismatches' => ['required', 'array', 'max:50'],
                'mismatches.*.type' => ['required', 'string', 'in:qty,price,tax,missing_line'],
                'mismatches.*.line_reference' => ['nullable', 'string', 'max:120'],
                'mismatches.*.severity' => ['nullable', 'string', 'in:info,warning,risk'],
                'mismatches.*.detail' => ['required', 'string', 'max:1000'],
                'mismatches.*.expected' => ['nullable'],
                'mismatches.*.actual' => ['nullable'],
                'recommendation' => ['required', 'array'],
                'recommendation.status' => ['required', 'string', 'in:approve,hold'],
                'recommendation.explanation' => ['required', 'string', 'max:2000'],
                'analysis_notes' => ['nullable', 'array', 'max:10'],
                'analysis_notes.*' => ['string', 'max:500'],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $mismatches = [];
        $rawMismatches = is_array($payload['mismatches'] ?? null) ? $payload['mismatches'] : [];

        foreach ($rawMismatches as $item) {
            if (! is_array($item)) {
                continue;
            }

            $mismatches[] = [
                'type' => (string) ($item['type'] ?? 'unmatched'),
                'line_reference' => $this->stringValue($item['line_reference'] ?? null),
                'severity' => $this->stringValue($item['severity'] ?? null),
                'detail' => (string) ($item['detail'] ?? ''),
                'expected' => $item['expected'] ?? null,
                'actual' => $item['actual'] ?? null,
            ];
        }

        return [
            'invoice_id' => (string) ($payload['invoice_id'] ?? ''),
            'matched_po_id' => $this->stringValue($payload['matched_po_id'] ?? null),
            'matched_receipt_ids' => array_values(array_map(static fn ($value): string => (string) $value, $payload['matched_receipt_ids'] ?? [])),
            'match_score' => isset($payload['match_score']) ? (float) $payload['match_score'] : null,
            'mismatches' => $mismatches,
            'recommendation' => [
                'status' => (string) ($payload['recommendation']['status'] ?? 'hold'),
                'explanation' => (string) ($payload['recommendation']['explanation'] ?? 'Review required.'),
            ],
            'analysis_notes' => $this->normalizeList($payload['analysis_notes'] ?? []),
        ];
    }

    private function resolveInvoice(AiActionDraft $draft, string $identifier, ?int $companyIdHint): Invoice
    {
        $context = $this->entityContext($draft);

        if ($context['entity_id'] !== null && $this->isInvoiceContext($context['entity_type'])) {
            $invoice = $this->invoiceQuery($companyIdHint)
                ->whereKey($context['entity_id'])
                ->first();

            if ($invoice instanceof Invoice) {
                return $invoice;
            }
        }

        if (is_numeric($identifier)) {
            $invoice = $this->invoiceQuery($companyIdHint)
                ->whereKey((int) $identifier)
                ->first();

            if ($invoice instanceof Invoice) {
                return $invoice;
            }
        }

        $invoice = $this->invoiceQuery($companyIdHint)
            ->where('invoice_number', $identifier)
            ->first();

        if (! $invoice instanceof Invoice) {
            throw $this->validationError('invoice_id', 'Invoice not found for this company.');
        }

        return $invoice;
    }

    private function invoiceQuery(?int $companyIdHint)
    {
        $query = Invoice::query();

        if ($companyIdHint !== null) {
            $query->forCompany($companyIdHint);
        }

        return $query;
    }

    private function isInvoiceContext(?string $entityType): bool
    {
        if ($entityType === null) {
            return false;
        }

        return Str::contains(Str::lower($entityType), 'invoice');
    }

    private function resolvePurchaseOrder(Invoice $invoice, ?string $identifier, int $companyId): ?PurchaseOrder
    {
        $invoice->loadMissing('purchaseOrder');

        if ($invoice->purchaseOrder instanceof PurchaseOrder) {
            return $invoice->purchaseOrder;
        }

        if ($identifier === null || $identifier === '') {
            return null;
        }

        if (is_numeric($identifier)) {
            $purchaseOrder = PurchaseOrder::query()
                ->forCompany($companyId)
                ->whereKey((int) $identifier)
                ->first();

            if ($purchaseOrder instanceof PurchaseOrder) {
                return $purchaseOrder;
            }
        }

        $purchaseOrder = PurchaseOrder::query()
            ->forCompany($companyId)
            ->where('po_number', $identifier)
            ->first();

        if (! $purchaseOrder instanceof PurchaseOrder) {
            throw $this->validationError('matched_po_id', 'Matched purchase order could not be found.');
        }

        return $purchaseOrder;
    }

    /**
     * @param list<string> $identifiers
     * @return Collection<int, GoodsReceiptNote>
     */
    private function resolveReceiptNotes(array $identifiers, int $companyId, ?int $purchaseOrderId): Collection
    {
        if ($identifiers === []) {
            return collect();
        }

        $query = GoodsReceiptNote::query()->forCompany($companyId);

        if ($purchaseOrderId !== null) {
            $query->where('purchase_order_id', $purchaseOrderId);
        }

        $numericIds = collect($identifiers)
            ->filter(static fn ($value) => is_numeric($value))
            ->map(static fn ($value) => (int) $value)
            ->values()
            ->all();

        $query->where(function ($builder) use ($identifiers, $numericIds): void {
            $builder->whereIn('number', $identifiers);

            if ($numericIds !== []) {
                $builder->orWhereIn('id', $numericIds);
            }
        });

        return $query->get();
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function buildSummaryDetails(array $validated, Collection $receiptNotes): array
    {
        $details = [];

        if ($validated['match_score'] !== null) {
            $details['match_score'] = $validated['match_score'];
        }

        if ($validated['analysis_notes'] !== []) {
            $details['analysis_notes'] = $validated['analysis_notes'];
        }

        $details['matched_receipt_ids'] = $validated['matched_receipt_ids'];

        $receiptSummary = $receiptNotes->map(static fn (GoodsReceiptNote $note): array => [
            'id' => $note->id,
            'number' => $note->number,
            'status' => $note->status,
        ])->values()->all();

        if ($receiptSummary !== []) {
            $details['receipts'] = $receiptSummary;
        }

        $details['recommendation'] = $validated['recommendation'];

        return $details;
    }

    private function mapResultFromMismatch(string $type): string
    {
        return match ($type) {
            'qty' => 'qty_mismatch',
            'price', 'tax' => 'price_mismatch',
            'missing_line' => 'unmatched',
            default => 'unmatched',
        };
    }
}
