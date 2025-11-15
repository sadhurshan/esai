<?php

namespace App\Http\Resources;

use App\Models\CreditNoteLine;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CreditNoteLine */
class CreditNoteLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $invoiceLine = $this->invoiceLine;
        $qtyInvoiced = (float) ($this->qty_invoiced ?? $invoiceLine?->quantity ?? 0);
        $unitPriceMinor = (int) ($this->unit_price_minor ?? $invoiceLine?->unit_price_minor ?? ($invoiceLine?->unit_price !== null
            ? (int) round((float) $invoiceLine->unit_price * 100)
            : 0));
        $currency = $this->currency ?? $invoiceLine?->currency ?? $this->creditNote?->currency ?? 'USD';
        $description = $this->description ?? $invoiceLine?->description;
        $uom = $this->uom ?? $invoiceLine?->uom;
        $qtyToCredit = (float) ($this->qty_to_credit ?? 0);
        $lineTotalMinor = (int) ($this->line_total_minor ?? (int) round($qtyToCredit * $unitPriceMinor));
        $previouslyCredited = (float) ($this->getAttribute('previously_credited_qty') ?? 0);

        return [
            'id' => $this->id,
            'invoice_line_id' => (int) $this->invoice_line_id,
            'description' => $description,
            'qty_invoiced' => $qtyInvoiced,
            'qty_to_credit' => $qtyToCredit,
            'qty_already_credited' => $previouslyCredited,
            'unit_price_minor' => $unitPriceMinor,
            'currency' => $currency,
            'uom' => $uom,
            'total_minor' => $lineTotalMinor,
        ];
    }
}
