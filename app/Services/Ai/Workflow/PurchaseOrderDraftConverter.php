<?php

namespace App\Services\Ai\Workflow;

use App\Enums\RfqItemAwardStatus;
use App\Exceptions\AiWorkflowException;
use App\Models\AiWorkflowStep;
use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\RFQ;
use App\Models\RfqItemAward;
use App\Models\Supplier;
use App\Support\Audit\AuditLogger;
use App\Support\CompanyContext;
use App\Support\Money\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PurchaseOrderDraftConverter
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * Persist approved PO draft payloads into purchase order records.
     */
    public function convert(AiWorkflowStep $step): array
    {
        $output = is_array($step->output_json) ? $step->output_json : [];
        $payload = is_array($output['payload'] ?? null) ? $output['payload'] : [];
        $companyId = (int) ($step->company_id ?? 0);

        if ($companyId <= 0 || $payload === []) {
            return $this->normalizeResponse(null, []);
        }

        return CompanyContext::forCompany($companyId, function () use ($companyId, $payload, $step): array {
            $rfq = $this->resolveRfq($step, $payload);
            $supplier = $this->resolveSupplier($companyId, $payload);
            $currency = $this->normalizeCurrency($payload['currency'] ?? $rfq?->currency ?? 'USD');
            $po = $this->upsertPurchaseOrder($companyId, $rfq, $supplier, $payload, $currency);

            $lines = $this->syncLines($po, $payload['line_items'] ?? [], $currency);
            $this->updateTotals($po, $lines, $payload['total_value'] ?? null, $currency);
            $po->refresh()->loadMissing(['lines']);

            $this->attachAwards($po, $supplier, $rfq);

            return $this->normalizeResponse($po, $lines);
        });
    }

    private function resolveRfq(AiWorkflowStep $step, array $payload): ?RFQ
    {
        $rfqId = $this->normalizeInt($step->input_json['rfq_id'] ?? $payload['rfq_id'] ?? null);

        return $rfqId !== null ? RFQ::query()->find($rfqId) : null;
    }

    private function resolveSupplier(int $companyId, array $payload): Supplier
    {
        $supplierBlock = is_array($payload['supplier'] ?? null) ? $payload['supplier'] : [];
        $supplierId = $this->normalizeInt($supplierBlock['supplier_id'] ?? $supplierBlock['id'] ?? null);

        if ($supplierId === null) {
            throw new AiWorkflowException('Supplier metadata missing from PO draft.');
        }

        $supplier = Supplier::query()->withTrashed()->forCompany($companyId)->find($supplierId);

        if (! $supplier instanceof Supplier) {
            throw new AiWorkflowException('Supplier not found for PO draft.');
        }

        return $supplier;
    }

    private function upsertPurchaseOrder(int $companyId, ?RFQ $rfq, Supplier $supplier, array $payload, string $currency): PurchaseOrder
    {
        $poNumber = trim((string) ($payload['po_number'] ?? ''));
        $existing = $poNumber !== ''
            ? PurchaseOrder::query()->withoutGlobalScopes()->where('po_number', $poNumber)->first()
            : null;

        if ($existing instanceof PurchaseOrder && (int) $existing->company_id !== $companyId) {
            $existing = null;
            $poNumber = '';
        }

        if ($existing instanceof PurchaseOrder) {
            $po = $existing;
        } else {
            $po = new PurchaseOrder();
            $po->company_id = $companyId;
            $po->po_number = $poNumber !== '' ? $poNumber : $this->generatePoNumber();
            $po->status = 'draft';
            $po->revision_no = $po->revision_no ?? 0;
        }

        $before = $po->exists ? Arr::only($po->getOriginal(), [
            'rfq_id', 'supplier_id', 'currency', 'incoterm', 'status', 'revision_no',
            'subtotal', 'tax_amount', 'total', 'subtotal_minor', 'tax_amount_minor', 'total_minor',
        ]) : [];

        $po->rfq_id = $rfq?->id;
        $po->supplier_id = $supplier->id;
        $po->currency = $currency;
        $po->incoterm = $rfq?->incoterm ?? $po->incoterm;
        $po->status = 'draft';
        $po->quote_id = $po->quote_id ?? null;
        $po->save();

        if ($before !== []) {
            $this->auditLogger->updated($po, $before, Arr::only($po->getChanges(), array_keys($before)));
        } else {
            $this->auditLogger->created($po, [
                'rfq_id' => $po->rfq_id,
                'supplier_id' => $po->supplier_id,
                'po_number' => $po->po_number,
            ]);
        }

        return $po;
    }

    /**
     * @param  array<int, array<string, mixed>>  $payloadLines
     * @return list<array{line_no:int,quantity:int,unit_price:float,subtotal:float,rfq_item_id:?int}>
     */
    private function syncLines(PurchaseOrder $po, array $payloadLines, string $currency): array
    {
        $normalized = [];
        $lineCounter = 1;

        foreach ($payloadLines as $rawLine) {
            if (! is_array($rawLine)) {
                continue;
            }

            $quantity = $this->normalizeInt($rawLine['quantity'] ?? null);
            $unitPrice = (float) ($rawLine['unit_price'] ?? 0);

            if ($quantity === null || $quantity <= 0) {
                continue;
            }

            $lineNo = $this->normalizeInt($rawLine['line_number'] ?? null) ?? $lineCounter;
            $description = (string) ($rawLine['description'] ?? 'Line ' . $lineCounter);
            $rfqItemId = $this->normalizeInt($rawLine['rfq_item_id'] ?? $rawLine['item_code'] ?? null);
            $subtotal = (float) ($rawLine['subtotal'] ?? ($quantity * $unitPrice));

            $normalized[] = [
                'line_no' => $lineNo,
                'description' => $description,
                'quantity' => $quantity,
                'uom' => (string) ($rawLine['uom'] ?? 'ea'),
                'unit_price' => $unitPrice,
                'unit_price_minor' => $this->decimalToMinor($unitPrice, $currency),
                'currency' => $currency,
                'delivery_date' => $rawLine['delivery_date'] ?? null,
                'rfq_item_id' => $rfqItemId,
                'subtotal' => $subtotal,
            ];

            $lineCounter++;
        }

        $existing = $po->lines()->get();

        foreach ($existing as $line) {
            $before = $line->toArray();
            $line->delete();
            $this->auditLogger->deleted($line, $before);
        }

        foreach ($normalized as $lineData) {
            $line = PurchaseOrderLine::query()->create([
                'purchase_order_id' => $po->id,
                'rfq_item_id' => $lineData['rfq_item_id'],
                'line_no' => $lineData['line_no'],
                'description' => $lineData['description'],
                'quantity' => $lineData['quantity'],
                'uom' => $lineData['uom'],
                'unit_price' => $lineData['unit_price'],
                'currency' => $lineData['currency'],
                'unit_price_minor' => $lineData['unit_price_minor'],
                'delivery_date' => $lineData['delivery_date'],
            ]);

            $this->auditLogger->created($line, [
                'purchase_order_id' => $po->id,
                'line_no' => $line->line_no,
            ]);
        }

        return $normalized;
    }

    /**
     * @param  list<array{subtotal:float}>  $lines
     */
    private function updateTotals(PurchaseOrder $po, array $lines, mixed $totalValue, string $currency): void
    {
        $subtotal = collect($lines)->sum(fn ($line) => (float) ($line['subtotal'] ?? 0));
        $grandTotal = (float) ($totalValue ?? $subtotal);
        $taxAmount = max(0, $grandTotal - $subtotal);

        $po->subtotal = number_format($subtotal, 2, '.', '');
        $po->tax_amount = number_format($taxAmount, 2, '.', '');
        $po->total = number_format($grandTotal, 2, '.', '');
        $po->subtotal_minor = $this->decimalToMinor($subtotal, $currency);
        $po->tax_amount_minor = $this->decimalToMinor($taxAmount, $currency);
        $po->total_minor = $this->decimalToMinor($grandTotal, $currency);
        $po->save();
    }

    private function attachAwards(PurchaseOrder $po, Supplier $supplier, ?RFQ $rfq): void
    {
        if (! $rfq instanceof RFQ) {
            return;
        }

        $awards = RfqItemAward::query()
            ->where('rfq_id', $rfq->id)
            ->where('supplier_id', $supplier->id)
            ->where('status', RfqItemAwardStatus::Awarded)
            ->whereNull('po_id')
            ->get();

        foreach ($awards as $award) {
            $before = Arr::only($award->getOriginal(), ['po_id']);
            $award->po_id = $po->id;
            $award->save();
            $this->auditLogger->updated($award, $before, ['po_id' => $po->id]);

            $line = $po->lines()
                ->where('rfq_item_id', $award->rfq_item_id)
                ->orderBy('line_no')
                ->first();

            if ($line instanceof PurchaseOrderLine) {
                $line->rfq_item_award_id = $award->id;
                $line->save();
            }
        }
    }

    private function normalizeCurrency(?string $currency): string
    {
        $code = strtoupper($currency ?? 'USD');

        return strlen($code) === 3 ? $code : 'USD';
    }

    private function decimalToMinor(float $amount, string $currency): int
    {
        $minorUnit = (int) (Currency::query()->where('code', strtoupper($currency))->value('minor_unit') ?? 2);

        return Money::fromDecimal($amount, $currency, $minorUnit)->amountMinor();
    }

    private function normalizeInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return null;
    }

    private function generatePoNumber(): string
    {
        do {
            $number = 'PO-' . Str::upper(Str::random(8));
        } while (PurchaseOrder::where('po_number', $number)->exists());

        return $number;
    }

    private function normalizeResponse(?PurchaseOrder $po, array $lines): array
    {
        if (! $po instanceof PurchaseOrder) {
            return [
                'purchase_order_id' => null,
                'po_number' => null,
                'line_count' => 0,
            ];
        }

        return [
            'purchase_order_id' => $po->id,
            'po_number' => $po->po_number,
            'line_count' => count($lines),
        ];
    }
}
