<?php

namespace App\Actions\Invoicing;

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Services\LineTaxSyncService;
use App\Services\TotalsCalculator;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class RecalculateInvoiceTotalsAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly TotalsCalculator $totalsCalculator,
        private readonly LineTaxSyncService $lineTaxSync,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        $invoice->loadMissing(['lines.taxes', 'purchaseOrder']);

        if ($invoice->company_id === null) {
            throw ValidationException::withMessages([
                'invoice_id' => ['Invoice company context is missing.'],
            ]);
        }

        $companyId = (int) $invoice->company_id;
        $currency = strtoupper($invoice->currency ?? $invoice->purchaseOrder?->currency ?? 'USD');

        $lineInputs = $invoice->lines
            ->map(function (InvoiceLine $line) use ($currency): array {
                $lineCurrency = strtoupper($line->currency ?? $currency);

                if ($lineCurrency !== $currency) {
                    throw ValidationException::withMessages([
                        'lines' => ['All invoice lines must use the same currency as the invoice.'],
                    ]);
                }

                $quantity = (float) $line->quantity;

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'lines' => ["Invoice line {$line->id} must have a quantity greater than zero."],
                    ]);
                }

                return [
                    'key' => $line->id,
                    'quantity' => $quantity,
                    'unit_price_minor' => $line->unit_price_minor ?? $this->decimalToMinor($line->unit_price, $currency),
                    'tax_code_ids' => $line->taxes
                        ->pluck('tax_code_id')
                        ->map(static fn ($value): int => (int) $value)
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $before = $invoice->only([
            'subtotal',
            'tax_amount',
            'total',
        ]);

        return $this->db->transaction(function () use ($invoice, $companyId, $currency, $lineInputs, $before): Invoice {
            $calculation = $this->totalsCalculator->calculate($companyId, $currency, $lineInputs);
            $minorUnit = (int) $calculation['minor_unit'];
            $lineResults = collect($calculation['lines'])->keyBy('key');

            foreach ($invoice->lines as $line) {
                $result = $lineResults->get($line->id);

                if ($result === null) {
                    continue;
                }

                $line->unit_price = Money::fromMinor($result['unit_price_minor'], $currency)->toDecimal($minorUnit);
                $line->unit_price_minor = (int) $result['unit_price_minor'];
                $line->currency = $currency;
                $line->save();

                $this->lineTaxSync->sync($line, (int) $invoice->company_id, $result['taxes']);
                $line->load('taxes.taxCode');
            }

            $invoice->currency = $currency;
            $invoice->subtotal = Money::fromMinor($calculation['totals']['subtotal_minor'], $currency)->toDecimal($minorUnit);
            $invoice->tax_amount = Money::fromMinor($calculation['totals']['tax_total_minor'], $currency)->toDecimal($minorUnit);
            $invoice->total = Money::fromMinor($calculation['totals']['grand_total_minor'], $currency)->toDecimal($minorUnit);
            $invoice->save();

            $this->auditLogger->updated($invoice, $before, [
                'subtotal' => $invoice->subtotal,
                'tax_amount' => $invoice->tax_amount,
                'total' => $invoice->total,
                'currency' => $invoice->currency,
            ]);

            return $invoice->load(['lines.taxes.taxCode', 'matches', 'document', 'purchaseOrder']);
        });
    }

    private function decimalToMinor(mixed $value, string $currency): int
    {
        $amount = (float) ($value ?? 0);
        $minorUnit = $this->minorUnit($currency);

        return Money::fromDecimal($amount, $currency, $minorUnit)->amountMinor();
    }

    private function minorUnit(string $currency): int
    {
        static $cache = [];

        $currency = strtoupper($currency);

        if (! array_key_exists($currency, $cache)) {
            $record = Currency::query()->where('code', $currency)->first();
            $cache[$currency] = $record?->minor_unit ?? 2;
        }

        return (int) $cache[$currency];
    }
}
