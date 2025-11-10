<?php

namespace App\Actions\Invoicing;

use App\Models\CreditNote;
use App\Models\Currency;
use App\Services\TotalsCalculator;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class RecalculateCreditNoteTotalsAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly TotalsCalculator $totalsCalculator,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CreditNote $creditNote): CreditNote
    {
        $creditNote->loadMissing(['invoice', 'purchaseOrder', 'goodsReceiptNote', 'documents']);

        if ($creditNote->company_id === null) {
            throw ValidationException::withMessages([
                'credit_note_id' => ['Credit note company context is missing.'],
            ]);
        }

        $companyId = (int) $creditNote->company_id;
        $currency = strtoupper($creditNote->currency ?? $creditNote->invoice?->currency ?? $creditNote->purchaseOrder?->currency ?? 'USD');

        $amountMinor = $creditNote->amount_minor ?? $this->decimalToMinor($creditNote->amount, $currency);

        $before = $creditNote->only(['amount', 'amount_minor', 'currency']);

        return $this->db->transaction(function () use ($creditNote, $companyId, $currency, $amountMinor, $before): CreditNote {
            $calculation = $this->totalsCalculator->calculate($companyId, $currency, [[
                'key' => $creditNote->id,
                'quantity' => 1,
                'unit_price_minor' => $amountMinor,
                'tax_code_ids' => [],
            ]]);

            $minorUnit = (int) $calculation['minor_unit'];
            $totalMinor = (int) $calculation['totals']['grand_total_minor'];

            $creditNote->currency = $currency;
            $creditNote->amount_minor = $totalMinor;
            $creditNote->amount = Money::fromMinor($totalMinor, $currency)->toDecimal($minorUnit);
            $creditNote->save();

            $this->auditLogger->updated($creditNote, $before, [
                'amount' => $creditNote->amount,
                'amount_minor' => $creditNote->amount_minor,
                'currency' => $creditNote->currency,
            ]);

            return $creditNote->fresh(['invoice', 'purchaseOrder', 'goodsReceiptNote', 'documents']);
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
