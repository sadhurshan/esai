<?php

namespace App\Services\Ai\Converters;

use App\Actions\Invoicing\CreateInvoiceAction;
use App\Enums\InvoiceStatus;
use App\Models\AiActionDraft;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceDraftConverter extends AbstractDraftConverter
{
    public function __construct(
        private readonly CreateInvoiceAction $createInvoiceAction,
        private readonly ValidationFactory $validator,
    ) {}

    /**
     * @return array{entity: mixed}
     */
    public function convert(AiActionDraft $draft, User $user): array
    {
        $result = $this->extractOutputAndPayload($draft, AiActionDraft::TYPE_INVOICE_DRAFT);
        $payload = $result['payload'];
        $output = $result['output'];

        $validated = $this->validatePayload($payload);
        $companyId = $user->company_id;

        if ($companyId === null) {
            throw ValidationException::withMessages([
                'company_id' => ['User is missing a company context.'],
            ]);
        }

        $purchaseOrder = $this->resolvePurchaseOrder($draft, $validated['po_id'], $companyId);
        $linePayloads = $this->buildInvoiceLines($purchaseOrder, $validated['line_items']);

        $invoicePayload = [
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $validated['due_date'],
            'currency' => $purchaseOrder->currency ?? 'USD',
            'lines' => $linePayloads,
        ];

        $context = [
            'company_id' => $companyId,
            'status' => InvoiceStatus::Draft->value,
            'created_by_type' => 'buyer',
            'created_by_id' => $user->id,
        ];

        $invoice = $this->createInvoiceAction->execute($user, $purchaseOrder, $invoicePayload, $context);

        if ($validated['notes'] !== null) {
            $invoice->review_note = $validated['notes'];
            $invoice->save();
        }

        $draft->forceFill([
            'entity_type' => $invoice->getMorphClass(),
            'entity_id' => $invoice->id,
        ])->save();

        return ['entity' => $invoice];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *     po_id: string,
     *     invoice_date: string,
     *     due_date: string,
     *     line_items: array<int, array{description: string, qty: float, unit_price: float, tax_rate: float}>,
     *     notes: ?string
     * }
     */
    private function validatePayload(array $payload): array
    {
        $validator = $this->validator->make(
            [
                'po_id' => $payload['po_id'] ?? null,
                'invoice_date' => $payload['invoice_date'] ?? null,
                'due_date' => $payload['due_date'] ?? null,
                'line_items' => $payload['line_items'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ],
            [
                'po_id' => ['required', 'string'],
                'invoice_date' => ['required', 'date'],
                'due_date' => ['required', 'date'],
                'line_items' => ['required', 'array', 'min:1', 'max:25'],
                'line_items.*.description' => ['required', 'string', 'max:200'],
                'line_items.*.qty' => ['required', 'numeric', 'gt:0'],
                'line_items.*.unit_price' => ['required', 'numeric', 'gte:0'],
                'line_items.*.tax_rate' => ['nullable', 'numeric', 'gte:0'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $lineItems = [];

        foreach ($payload['line_items'] as $item) {
            $lineItems[] = [
                'description' => (string) $item['description'],
                'qty' => (float) $item['qty'],
                'unit_price' => (float) $item['unit_price'],
                'tax_rate' => isset($item['tax_rate']) ? (float) $item['tax_rate'] : 0.0,
            ];
        }

        return [
            'po_id' => (string) $payload['po_id'],
            'invoice_date' => (string) $payload['invoice_date'],
            'due_date' => (string) $payload['due_date'],
            'line_items' => $lineItems,
            'notes' => $this->stringValue($payload['notes'] ?? null),
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

        $normalized = Str::lower($entityType);

        return in_array($normalized, ['purchase_order', 'purchaseorder', 'po'], true);
    }

    /**
     * @param array<int, array{description: string, qty: float, unit_price: float, tax_rate: float}> $lineItems
     * @return array<int, array<string, mixed>>
     */
    private function buildInvoiceLines(PurchaseOrder $purchaseOrder, array $lineItems): array
    {
        $purchaseOrder->loadMissing('lines');

        $poLines = $purchaseOrder->lines;

        if ($poLines->isEmpty()) {
            throw $this->validationError('po_id', 'Purchase order does not have any lines to invoice.');
        }

        /** @var Collection<int, PurchaseOrderLine> $sorted */
        $sorted = $poLines
            ->sortBy(static fn (PurchaseOrderLine $line) => $line->line_no ?? $line->id)
            ->values();

        $used = [];
        $lines = [];

        foreach ($lineItems as $index => $item) {
            $poLine = $this->matchPurchaseOrderLine($sorted, $item['description'], $index, $used);

            if (! $poLine instanceof PurchaseOrderLine) {
                throw $this->validationError("line_items.{$index}", 'Unable to map invoice line to a purchase order line.');
            }

            $lines[] = [
                'po_line_id' => $poLine->id,
                'quantity' => $this->normalizeQuantity($item['qty'], $index),
                'unit_price' => $this->normalizeUnitPrice($item['unit_price'], $index),
                'description' => $item['description'],
                'uom' => $poLine->uom,
                'tax_code_ids' => [],
            ];
        }

        return $lines;
    }

    /**
     * @param array<int, bool> $used
     */
    private function matchPurchaseOrderLine(Collection $poLines, string $description, int $index, array &$used): ?PurchaseOrderLine
    {
        $normalizedDescription = Str::of($description)->lower()->trim()->value();

        foreach ($poLines as $line) {
            if (isset($used[$line->id])) {
                continue;
            }

            $lineDescription = Str::of((string) $line->description)->lower()->trim()->value();

            if ($normalizedDescription !== '' && $lineDescription === $normalizedDescription) {
                $used[$line->id] = true;

                return $line;
            }
        }

        $candidate = $poLines->get($index);

        if ($candidate instanceof PurchaseOrderLine && ! isset($used[$candidate->id])) {
            $used[$candidate->id] = true;

            return $candidate;
        }

        foreach ($poLines as $line) {
            if (isset($used[$line->id])) {
                continue;
            }

            $used[$line->id] = true;

            return $line;
        }

        return null;
    }

    private function normalizeQuantity(float $value, int $index): int
    {
        if ($value <= 0) {
            throw $this->validationError("line_items.{$index}.qty", 'Quantity must be greater than zero.');
        }

        $quantity = (int) round($value);

        return max(1, $quantity);
    }

    private function normalizeUnitPrice(float $value, int $index): float
    {
        if ($value < 0) {
            throw $this->validationError("line_items.{$index}.unit_price", 'Unit price must be zero or greater.');
        }

        return round($value, 4);
    }
}
