<?php

namespace App\Services\Ai\Converters;

use App\Actions\Receiving\CreateGoodsReceiptNoteAction;
use App\Models\AiActionDraft;
use App\Models\GoodsReceiptNote;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoodsReceiptDraftConverter extends AbstractDraftConverter
{
    public function __construct(
        private readonly CreateGoodsReceiptNoteAction $createGoodsReceiptNoteAction,
        private readonly ValidationFactory $validator,
    ) {}

    /**
     * @return array{entity: GoodsReceiptNote}
     */
    public function convert(AiActionDraft $draft, User $user): array
    {
        $result = $this->extractOutputAndPayload($draft, AiActionDraft::TYPE_RECEIPT_DRAFT);
        $payload = $result['payload'];
        $validated = $this->validatePayload($payload);

        $companyId = $user->company_id;

        if ($companyId === null) {
            throw ValidationException::withMessages([
                'company_id' => ['User is missing a company context.'],
            ]);
        }

        $purchaseOrder = $this->resolvePurchaseOrder($draft, $validated['po_id'], $companyId);
        $linesPayload = $this->buildLinePayloads($purchaseOrder, $validated['line_items']);

        $notePayload = array_filter([
            'inspected_at' => $validated['received_date'],
            'reference' => $validated['reference'],
            'notes' => $validated['notes'],
            'status' => $validated['status'],
            'lines' => $linesPayload,
        ], static fn ($value) => $value !== null && $value !== '');

        $inspectorId = $this->resolveInspectorId($draft);

        if ($inspectorId !== null) {
            $notePayload['inspected_by_id'] = $inspectorId;
        }

        $result = $this->createGoodsReceiptNoteAction->execute($user, $companyId, $purchaseOrder, $notePayload);
        $note = $result['note'];

        $draft->forceFill([
            'entity_type' => $note->getMorphClass(),
            'entity_id' => $note->id,
        ])->save();

        return ['entity' => $note];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *     po_id: string,
     *     received_date: string,
     *     reference: ?string,
     *     status: ?string,
     *     notes: ?string,
     *     line_items: list<array{
     *         po_line_id: string,
     *         line_number: ?int,
     *         description: string,
     *         received_qty: float,
     *         accepted_qty: ?float,
     *         rejected_qty: ?float,
     *         issues: list<string>,
     *         notes: ?string
     *     }>
     * }
     */
    private function validatePayload(array $payload): array
    {
        $validator = $this->validator->make(
            [
                'po_id' => $payload['po_id'] ?? null,
                'received_date' => $payload['received_date'] ?? null,
                'reference' => $payload['reference'] ?? null,
                'status' => $payload['status'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'line_items' => $payload['line_items'] ?? null,
            ],
            [
                'po_id' => ['required', 'string', 'max:120'],
                'received_date' => ['required', 'date'],
                'reference' => ['nullable', 'string', 'max:120'],
                'status' => ['nullable', 'string', 'max:30'],
                'notes' => ['nullable', 'string', 'max:2000'],
                'line_items' => ['required', 'array', 'min:1', 'max:25'],
                'line_items.*.po_line_id' => ['required', 'string', 'max:120'],
                'line_items.*.line_number' => ['nullable', 'numeric', 'gte:1'],
                'line_items.*.description' => ['required', 'string', 'max:200'],
                'line_items.*.received_qty' => ['required', 'numeric', 'gt:0'],
                'line_items.*.accepted_qty' => ['nullable', 'numeric', 'gte:0'],
                'line_items.*.rejected_qty' => ['nullable', 'numeric', 'gte:0'],
                'line_items.*.issues' => ['nullable', 'array', 'max:10'],
                'line_items.*.issues.*' => ['string', 'max:200'],
                'line_items.*.notes' => ['nullable', 'string', 'max:1000'],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $lineItems = [];
        $rawLineItems = is_array($payload['line_items'] ?? null) ? $payload['line_items'] : [];

        foreach ($rawLineItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            $lineItems[] = [
                'po_line_id' => (string) ($item['po_line_id'] ?? ''),
                'line_number' => isset($item['line_number']) && is_numeric($item['line_number'])
                    ? (int) round((float) $item['line_number'])
                    : null,
                'description' => (string) ($item['description'] ?? ''),
                'received_qty' => isset($item['received_qty']) ? (float) $item['received_qty'] : 0.0,
                'accepted_qty' => isset($item['accepted_qty']) ? (float) $item['accepted_qty'] : null,
                'rejected_qty' => isset($item['rejected_qty']) ? (float) $item['rejected_qty'] : null,
                'issues' => $this->normalizeList($item['issues'] ?? []),
                'notes' => $this->stringValue($item['notes'] ?? null),
            ];
        }

        if ($lineItems === []) {
            throw $this->validationError('line_items', 'At least one receipt line is required.');
        }

        return [
            'po_id' => (string) ($payload['po_id'] ?? ''),
            'received_date' => (string) ($payload['received_date'] ?? ''),
            'reference' => $this->stringValue($payload['reference'] ?? null),
            'status' => $this->stringValue($payload['status'] ?? null),
            'notes' => $this->stringValue($payload['notes'] ?? null),
            'line_items' => $lineItems,
        ];
    }

    private function resolvePurchaseOrder(AiActionDraft $draft, string $identifier, int $companyId): PurchaseOrder
    {
        $context = $this->entityContext($draft);

        if ($context['entity_id'] !== null && $this->isPurchaseOrderContext($context['entity_type'])) {
            $purchaseOrder = PurchaseOrder::query()
                ->forCompany($companyId)
                ->whereKey($context['entity_id'])
                ->first();

            if ($purchaseOrder instanceof PurchaseOrder) {
                return $purchaseOrder;
            }
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
            throw $this->validationError('po_id', 'Purchase order not found for this company.');
        }

        return $purchaseOrder;
    }

    private function isPurchaseOrderContext(?string $entityType): bool
    {
        if ($entityType === null) {
            return false;
        }

        $normalized = Str::lower(trim($entityType));

        return in_array($normalized, ['purchase_order', 'purchaseorder', 'po'], true);
    }

    /**
     * @param list<array{
     *     po_line_id: string,
     *     line_number: ?int,
     *     description: string,
     *     received_qty: float,
     *     accepted_qty: ?float,
     *     rejected_qty: ?float,
     *     issues: list<string>,
     *     notes: ?string
     * }> $lineItems
     * @return list<array<string, mixed>>
     */
    private function buildLinePayloads(PurchaseOrder $purchaseOrder, array $lineItems): array
    {
        $purchaseOrder->loadMissing('lines');
        $lines = $purchaseOrder->lines;

        if ($lines->isEmpty()) {
            throw $this->validationError('po_id', 'Purchase order does not have any lines to receive.');
        }

        $payloads = [];

        foreach ($lineItems as $index => $item) {
            $poLine = $this->matchPurchaseOrderLine($lines, $item);

            if (! $poLine instanceof PurchaseOrderLine) {
                throw $this->validationError("line_items.{$index}.po_line_id", 'Unable to map receipt line to a purchase order line.');
            }

            $receivedQty = $this->normalizeQty($item['received_qty'], "line_items.{$index}.received_qty", false);
            $acceptedQty = $item['accepted_qty'] !== null
                ? $this->normalizeQty($item['accepted_qty'], "line_items.{$index}.accepted_qty", true)
                : $receivedQty;
            $rejectedQty = $item['rejected_qty'] !== null
                ? $this->normalizeQty($item['rejected_qty'], "line_items.{$index}.rejected_qty", true)
                : max(0, $receivedQty - $acceptedQty);

            if ($acceptedQty > $receivedQty) {
                $acceptedQty = $receivedQty;
            }

            if ($rejectedQty > $receivedQty) {
                $rejectedQty = max(0, $receivedQty - $acceptedQty);
            }

            if ($acceptedQty + $rejectedQty !== $receivedQty) {
                $rejectedQty = max(0, $receivedQty - $acceptedQty);
            }

            $payloads[] = array_filter([
                'purchase_order_line_id' => $poLine->id,
                'received_qty' => $receivedQty,
                'accepted_qty' => $acceptedQty,
                'rejected_qty' => $rejectedQty,
                'defect_notes' => $this->buildDefectNotes($item),
            ], static fn ($value) => $value !== null && $value !== '');
        }

        return $payloads;
    }

    /**
     * @param Collection<int, PurchaseOrderLine> $lines
     * @param array{
     *     po_line_id: string,
     *     line_number: ?int,
     *     description: string
     * } $item
     */
    private function matchPurchaseOrderLine(Collection $lines, array $item): ?PurchaseOrderLine
    {
        $poLineId = $item['po_line_id'];

        if (is_numeric($poLineId)) {
            $match = $lines->firstWhere('id', (int) $poLineId);

            if ($match instanceof PurchaseOrderLine) {
                return $match;
            }
        }

        if ($item['line_number'] !== null) {
            $match = $lines->first(static fn (PurchaseOrderLine $line) => (int) $line->line_no === $item['line_number']);

            if ($match instanceof PurchaseOrderLine) {
                return $match;
            }
        }

        $normalizedId = Str::of($poLineId)->trim()->lower()->value();

        if ($normalizedId !== '') {
            $match = $lines->first(static function (PurchaseOrderLine $line) use ($normalizedId): bool {
                $lineNumber = Str::of((string) $line->line_no)->trim()->lower()->value();
                $description = Str::of((string) $line->description)->trim()->lower()->value();

                return $normalizedId === $lineNumber || $normalizedId === $description;
            });

            if ($match instanceof PurchaseOrderLine) {
                return $match;
            }
        }

        $normalizedDescription = Str::of($item['description'])->trim()->lower()->value();

        if ($normalizedDescription !== '') {
            $match = $lines->first(static fn (PurchaseOrderLine $line) => Str::of((string) $line->description)->trim()->lower()->value() === $normalizedDescription);

            if ($match instanceof PurchaseOrderLine) {
                return $match;
            }
        }

        return null;
    }

    private function normalizeQty(float $value, string $field, bool $allowZero): int
    {
        if (! $allowZero && $value <= 0) {
            throw $this->validationError($field, 'Quantity must be greater than zero.');
        }

        if ($value < 0) {
            throw $this->validationError($field, 'Quantity cannot be negative.');
        }

        $quantity = (int) round($value);

        if (! $allowZero && $quantity <= 0) {
            throw $this->validationError($field, 'Quantity must be greater than zero.');
        }

        if ($allowZero) {
            return max(0, $quantity);
        }

        return max(1, $quantity);
    }

    /**
     * @param array{issues: list<string>, notes: ?string} $item
     */
    private function buildDefectNotes(array $item): ?string
    {
        $notes = [];

        if ($item['notes'] !== null) {
            $notes[] = $item['notes'];
        }

        if ($item['issues'] !== []) {
            $notes[] = 'Issues: ' . implode('; ', $item['issues']);
        }

        if ($notes === []) {
            return null;
        }

        $compiled = trim(implode(PHP_EOL, $notes));

        return $compiled === '' ? null : $compiled;
    }

    private function resolveInspectorId(AiActionDraft $draft): ?int
    {
        $inputs = $this->inputs($draft);
        $candidate = $inputs['inspected_by_id']
            ?? $inputs['inspector_id']
            ?? $inputs['receiver_id']
            ?? null;

        if ($candidate === null) {
            return null;
        }

        if (is_int($candidate) && $candidate > 0) {
            return $candidate;
        }

        if (is_numeric($candidate)) {
            $value = (int) $candidate;

            return $value > 0 ? $value : null;
        }

        return null;
    }
}
