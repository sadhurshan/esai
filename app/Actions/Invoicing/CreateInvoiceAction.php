<?php

namespace App\Actions\Invoicing;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Documents\DocumentStorer;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateInvoiceAction
{
    public function __construct(
        private readonly DocumentStorer $documentStorer,
        private readonly AuditLogger $auditLogger,
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(User $user, PurchaseOrder $purchaseOrder, array $payload): Invoice
    {
        $companyId = $user->company_id;

        if ($companyId === null) {
            throw ValidationException::withMessages([
                'company_id' => ['User company context missing.'],
            ]);
        }

        if ((int) $purchaseOrder->company_id !== (int) $companyId) {
            throw ValidationException::withMessages([
                'purchase_order_id' => ['Purchase order not found for this company.'],
            ]);
        }

        $supplierId = $this->resolveSupplierId($purchaseOrder, $payload);

        if ($supplierId === null) {
            throw ValidationException::withMessages([
                'supplier_id' => ['Unable to resolve supplier for this invoice.'],
            ]);
        }

        /** @var Collection<int, array<string, mixed>> $linesPayload */
        $linesPayload = collect($payload['lines'] ?? []);

        if ($linesPayload->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => ['At least one invoice line is required.'],
            ]);
        }

        $invoiceNumber = $payload['invoice_number'] ?? $this->generateInvoiceNumber($companyId);

        $currency = $payload['currency'] ?? $purchaseOrder->currency ?? 'USD';

        /** @var UploadedFile|null $document */
        $document = $payload['document'] ?? null;

    return $this->db->transaction(function () use ($companyId, $user, $purchaseOrder, $supplierId, $invoiceNumber, $currency, $linesPayload, $document): Invoice {
            $resolvedLines = $this->resolveLines($purchaseOrder, $linesPayload);

            $totals = $this->calculateTotals($purchaseOrder, $resolvedLines);

            $invoice = Invoice::create([
                'company_id' => $companyId,
                'purchase_order_id' => $purchaseOrder->id,
                'supplier_id' => $supplierId,
                'invoice_number' => $invoiceNumber,
                'currency' => strtoupper($currency),
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax'],
                'total' => $totals['total'],
                'status' => 'pending',
            ]);

            $resolvedLines->each(function (array $line) use ($invoice): void {
                InvoiceLine::create([
                    'invoice_id' => $invoice->id,
                    'po_line_id' => $line['po_line_id'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'uom' => $line['uom'],
                    'unit_price' => $line['unit_price'],
                ]);
            });

            if ($document instanceof UploadedFile) {
                $stored = $this->documentStorer->store(
                    $user,
                    $document,
                    'financial',
                    $companyId,
                    $invoice->getMorphClass(),
                    $invoice->id,
                    [
                        'kind' => 'invoice',
                        'visibility' => 'company',
                        'meta' => ['context' => 'invoice_attachment'],
                    ]
                );

                $invoice->document_id = $stored->id;
                $invoice->save();
            }

            $company = Company::query()
                ->whereKey($companyId)
                ->lockForUpdate()
                ->first();

            if ($company !== null) {
                $company->increment('invoices_monthly_used');
            }

            $invoice->load(['lines', 'document']);

            $this->auditLogger->created($invoice, ['user_id' => $user->id]);

            return $invoice;
        });
    }

    private function resolveSupplierId(PurchaseOrder $purchaseOrder, array $payload): ?int
    {
        if (isset($payload['supplier_id'])) {
            $supplierId = (int) $payload['supplier_id'];

            return Supplier::query()->whereKey($supplierId)->exists() ? $supplierId : null;
        }

        $purchaseOrder->loadMissing('quote');

        if ($purchaseOrder->quote?->supplier_id) {
            return (int) $purchaseOrder->quote->supplier_id;
        }

        return null;
    }

    private function generateInvoiceNumber(int $companyId): string
    {
        $date = now()->format('ymd');

        do {
            $suffix = Str::upper(Str::random(4));
            $number = sprintf('INV-%s-%s', $date, $suffix);
            $exists = Invoice::query()
                ->where('company_id', $companyId)
                ->where('invoice_number', $number)
                ->exists();
        } while ($exists);

        return $number;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function resolveLines(PurchaseOrder $purchaseOrder, Collection $lines): Collection
    {
        $purchaseOrder->loadMissing('lines');

        return $lines->map(function (array $line) use ($purchaseOrder): array {
            $poLineId = (int) ($line['po_line_id'] ?? 0);

            /** @var PurchaseOrderLine|null $poLine */
            $poLine = $purchaseOrder->lines->firstWhere('id', $poLineId);

            if ($poLine === null) {
                throw ValidationException::withMessages([
                    'lines' => ["Purchase order line {$poLineId} is invalid for this purchase order."],
                ]);
            }

            $quantity = isset($line['quantity']) ? (int) $line['quantity'] : (int) $poLine->quantity;
            $unitPrice = isset($line['unit_price']) ? (float) $line['unit_price'] : (float) $poLine->unit_price;

            return [
                'po_line_id' => $poLineId,
                'description' => $line['description'] ?? $poLine->description,
                'quantity' => $quantity,
                'uom' => $line['uom'] ?? $poLine->uom,
                'unit_price' => $unitPrice,
            ];
        });
    }

    /**
     * @param Collection<int, array<string, mixed>> $lines
     * @return array{subtotal: float, tax: float, total: float}
     */
    private function calculateTotals(PurchaseOrder $purchaseOrder, Collection $lines): array
    {
        $subtotal = $lines->reduce(function (float $carry, array $line): float {
            return $carry + ($line['quantity'] * $line['unit_price']);
        }, 0.0);

        $taxPercent = (float) ($purchaseOrder->tax_percent ?? 0);
        $taxAmount = round($subtotal * ($taxPercent / 100), 2);
        $total = round($subtotal + $taxAmount, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'tax' => $taxAmount,
            'total' => $total,
        ];
    }
}
