<?php

namespace App\Http\Resources;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CreditNote */
class CreditNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $status = $this->status instanceof CreditNoteStatus ? $this->status->value : $this->status;

        return [
            'id' => $this->id,
            'company_id' => (int) $this->company_id,
            'invoice_id' => (int) $this->invoice_id,
            'purchase_order_id' => (int) $this->purchase_order_id,
            'grn_id' => $this->grn_id !== null ? (int) $this->grn_id : null,
            'credit_number' => $this->credit_number,
            'currency' => $this->currency,
            'amount' => $this->amount,
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
}
