<?php

namespace App\Http\Resources;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use App\Models\Currency;
use App\Support\Money\Money;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CreditNote */
class CreditNoteResource extends JsonResource
{
    private static array $minorUnitCache = [];

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $status = $this->status instanceof CreditNoteStatus ? $this->status->value : $this->status;
        $currency = strtoupper($this->currency ?? $this->invoice?->currency ?? 'USD');
        $minorUnit = $this->minorUnitFor($currency);

        $amountMinor = $this->amount_minor !== null
            ? (int) $this->amount_minor
            : Money::fromDecimal((float) $this->amount, $currency, $minorUnit)->amountMinor();

        $amount = Money::fromMinor($amountMinor, $currency)->toDecimal($minorUnit);

        return [
            'id' => $this->id,
            'company_id' => (int) $this->company_id,
            'invoice_id' => (int) $this->invoice_id,
            'purchase_order_id' => (int) $this->purchase_order_id,
            'grn_id' => $this->grn_id !== null ? (int) $this->grn_id : null,
            'credit_number' => $this->credit_number,
            'currency' => $currency,
            'amount' => $amount,
            'amount_minor' => $amountMinor,
            'reason' => $this->reason,
            'status' => $status,
            'review_comment' => $this->review_comment,
            'issued_by' => $this->issued_by !== null ? (int) $this->issued_by : null,
            'approved_by' => $this->approved_by !== null ? (int) $this->approved_by : null,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'attachments' => DocumentResource::collection($this->whenLoaded('documents')),
            'invoice' => $this->whenLoaded('invoice', fn () => InvoiceResource::make($this->invoice)),
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn () => PurchaseOrderResource::make($this->purchaseOrder)),
            'goods_receipt_note' => $this->whenLoaded('goodsReceiptNote', fn () => GoodsReceiptNoteResource::make($this->goodsReceiptNote)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function minorUnitFor(string $currency): int
    {
        $currency = strtoupper($currency);

        if (! array_key_exists($currency, self::$minorUnitCache)) {
            $record = Currency::query()->where('code', $currency)->first();
            self::$minorUnitCache[$currency] = $record?->minor_unit ?? 2;
        }

        return (int) self::$minorUnitCache[$currency];
    }
}
