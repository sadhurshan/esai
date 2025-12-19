<?php

namespace App\Actions\Invoicing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use App\Services\TotalsCalculator;
use App\Services\LineTaxSyncService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class UpdateInvoiceAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DatabaseManager $db,
        private readonly TotalsCalculator $totalsCalculator,
        private readonly LineTaxSyncService $lineTaxSync,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{
     *     company_id?:int,
     *     editable_statuses?:array<int, string>,
     *     allowed_status_transitions?:array<int, string>,
     *     prevent_revert_status?:string|null
     * }|null  $context
     */
    public function execute(User $user, Invoice $invoice, array $payload, ?array $context = null): Invoice
    {
        $context ??= [];

        $companyId = $context['company_id'] ?? $user->company_id;

        if ($companyId === null || (int) $invoice->company_id !== (int) $companyId) {
            throw ValidationException::withMessages([
                'invoice_id' => ['Invoice not found for this company.'],
            ]);
        }

        $invoice->loadMissing(['lines', 'purchaseOrder']);

        $linesPayload = collect($payload['lines'] ?? []);
        $taxOverrides = $linesPayload
            ->filter(static fn (array $line): bool => array_key_exists('tax_code_ids', $line))
            ->mapWithKeys(function (array $line): array {
                $id = (int) ($line['id'] ?? 0);

                return [
                    $id => array_values(array_filter(
                        array_map('intval', $line['tax_code_ids'] ?? []),
                        static fn (int $value) => $value > 0
                    )),
                ];
            });
        $unitPriceOverrides = $linesPayload
            ->filter(static fn (array $line): bool => array_key_exists('unit_price', $line))
            ->mapWithKeys(static fn (array $line): array => [
                (int) ($line['id'] ?? 0) => true,
            ]);

        $editableStatuses = $context['editable_statuses'] ?? [
            InvoiceStatus::Draft->value,
            InvoiceStatus::BuyerReview->value,
            InvoiceStatus::Rejected->value,
        ];

        if ($linesPayload->isNotEmpty() && ! in_array($invoice->status, $editableStatuses, true)) {
            throw ValidationException::withMessages([
                'status' => ['Invoice lines can only be edited while the invoice is in an editable state.'],
            ]);
        }

        $targetStatus = $payload['status'] ?? null;

        $allowedStatusTransitions = $context['allowed_status_transitions'] ?? InvoiceStatus::values();

        if ($targetStatus !== null && ! in_array($targetStatus, $allowedStatusTransitions, true)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid invoice status provided.'],
            ]);
        }

        $beforeSnapshot = $invoice->toArray();

        $preventRevertStatus = $context['prevent_revert_status'] ?? InvoiceStatus::Draft->value;

        return $this->db->transaction(function () use (
            $invoice,
            $linesPayload,
            $taxOverrides,
            $unitPriceOverrides,
            $targetStatus,
            $user,
            $beforeSnapshot,
            $preventRevertStatus,
        ): Invoice {
            $before = $beforeSnapshot;

            if ($linesPayload->isNotEmpty()) {
                $this->applyLineUpdates($invoice, $linesPayload);
            }

            if ($targetStatus !== null && $targetStatus !== $invoice->status) {
                if (
                    $preventRevertStatus !== null
                    && $invoice->status !== $preventRevertStatus
                    && $targetStatus === $preventRevertStatus
                ) {
                    throw ValidationException::withMessages([
                        'status' => [sprintf('Cannot revert invoice to %s.', $preventRevertStatus)],
                    ]);
                }

                $invoice->status = $targetStatus;
            }

            $invoice->loadMissing(['lines.taxes', 'purchaseOrder']);

            $lineInputs = $invoice->lines
                ->sortBy('id')
                ->values()
                ->map(function (InvoiceLine $line) use ($taxOverrides, $unitPriceOverrides): array {
                    $taxCodeIds = $taxOverrides->get($line->id, $line->taxes->pluck('tax_code_id')->all());

                    $base = [
                        'key' => $line->id,
                        'quantity' => $line->quantity,
                        'unit_price' => (float) $line->unit_price,
                        'tax_code_ids' => $taxCodeIds,
                    ];

                    if (! $unitPriceOverrides->has($line->id)) {
                        $base['unit_price_minor'] = $line->unit_price_minor;
                    }

                    return $base;
                })
                ->all();

            $calculation = $this->totalsCalculator->calculate(
                (int) $invoice->company_id,
                $invoice->currency,
                $lineInputs
            );

            $minorUnit = $calculation['minor_unit'];
            $lineResults = collect($calculation['lines'])->keyBy('key');

            foreach ($invoice->lines as $line) {
                $result = $lineResults->get($line->id);

                if ($result === null) {
                    throw ValidationException::withMessages([
                        'lines' => ['Unable to recalculate taxes for one or more lines.'],
                    ]);
                }

                $unitPrice = $this->formatMinor($result['unit_price_minor'], $invoice->currency, $minorUnit);

                $line->unit_price = $unitPrice;
                $line->unit_price_minor = $result['unit_price_minor'];
                $line->currency = $invoice->currency;
                $line->save();

                $this->lineTaxSync->sync($line, (int) $invoice->company_id, $result['taxes']);
            }

            $invoice->subtotal = $this->formatMinor($calculation['totals']['subtotal_minor'], $invoice->currency, $minorUnit);
            $invoice->tax_amount = $this->formatMinor($calculation['totals']['tax_total_minor'], $invoice->currency, $minorUnit);
            $invoice->total = $this->formatMinor($calculation['totals']['grand_total_minor'], $invoice->currency, $minorUnit);

            $invoice->save();
            $invoice->load(['lines.taxes.taxCode', 'document']);

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

    private function formatMinor(int $amountMinor, string $currency, int $minorUnit): string
    {
        return Money::fromMinor($amountMinor, strtoupper($currency))->toDecimal($minorUnit);
    }
}
