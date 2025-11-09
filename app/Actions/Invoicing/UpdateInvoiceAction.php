<?php

namespace App\Actions\Invoicing;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class UpdateInvoiceAction
{
    private const ALLOWED_STATUSES = ['pending', 'paid', 'overdue', 'disputed'];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(User $user, Invoice $invoice, array $payload): Invoice
    {
        if ($user->company_id === null || (int) $invoice->company_id !== (int) $user->company_id) {
            throw ValidationException::withMessages([
                'invoice_id' => ['Invoice not found for this company.'],
            ]);
        }

        $invoice->loadMissing(['lines', 'purchaseOrder']);

        $linesPayload = collect($payload['lines'] ?? []);

        if ($linesPayload->isNotEmpty() && $invoice->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Only pending invoices can be edited.'],
            ]);
        }

        $targetStatus = $payload['status'] ?? null;

        if ($targetStatus !== null && ! in_array($targetStatus, self::ALLOWED_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid invoice status provided.'],
            ]);
        }

        $beforeSnapshot = $invoice->toArray();

        return $this->db->transaction(function () use ($invoice, $linesPayload, $targetStatus, $user, $beforeSnapshot): Invoice {
            $before = $beforeSnapshot;

            if ($linesPayload->isNotEmpty()) {
                $this->applyLineUpdates($invoice, $linesPayload);
            }

            if ($targetStatus !== null && $targetStatus !== $invoice->status) {
                if ($invoice->status !== 'pending' && $targetStatus === 'pending') {
                    throw ValidationException::withMessages([
                        'status' => ['Cannot revert invoice to pending.'],
                    ]);
                }

                $invoice->status = $targetStatus;
            }

            $totals = $this->recalculateTotals($invoice->purchaseOrder, $invoice->lines);

            $invoice->subtotal = $totals['subtotal'];
            $invoice->tax_amount = $totals['tax'];
            $invoice->total = $totals['total'];

            $invoice->save();
            $invoice->load(['lines', 'document']);

            $this->auditLogger->updated($invoice, $before, $invoice->toArray(), ['user_id' => $user->id]);

            return $invoice;
        });
    }

    /**
     * @param Collection<int, array<string, mixed>> $linesPayload
     */
    private function applyLineUpdates(Invoice $invoice, Collection $linesPayload): void
    {
        $linesPayload->each(function (array $line) use ($invoice): void {
            $lineId = (int) ($line['id'] ?? 0);

            /** @var InvoiceLine|null $invoiceLine */
            $invoiceLine = $invoice->lines->firstWhere('id', $lineId);

            if ($invoiceLine === null) {
                throw ValidationException::withMessages([
                    'lines' => ["Invoice line {$lineId} was not found on this invoice."],
                ]);
            }

            if (array_key_exists('description', $line)) {
                $invoiceLine->description = (string) $line['description'];
            }

            if (array_key_exists('unit_price', $line)) {
                $invoiceLine->unit_price = (float) $line['unit_price'];
            }

            if (array_key_exists('quantity', $line)) {
                $invoiceLine->quantity = (int) $line['quantity'];
            }

            $invoiceLine->save();
        });

        $invoice->load('lines');
    }

    /**
     * @return array{subtotal: float, tax: float, total: float}
     */
    private function recalculateTotals(?PurchaseOrder $purchaseOrder, Collection $lines): array
    {
        $subtotal = $lines->reduce(function (float $carry, InvoiceLine $line): float {
            return $carry + ($line->quantity * (float) $line->unit_price);
        }, 0.0);

        $taxPercent = (float) ($purchaseOrder?->tax_percent ?? 0);
        $taxAmount = round($subtotal * ($taxPercent / 100), 2);
        $total = round($subtotal + $taxAmount, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'tax' => $taxAmount,
            'total' => $total,
        ];
    }
}
