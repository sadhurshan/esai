<?php

namespace App\Http\Resources;

use App\Enums\RmaStatus;
use App\Models\Rma;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Rma */
class RmaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $status = $this->status instanceof RmaStatus ? $this->status->value : $this->status;

        return [
            'id' => $this->id,
            'company_id' => (int) $this->company_id,
            'purchase_order_id' => (int) $this->purchase_order_id,
            'purchase_order_line_id' => $this->purchase_order_line_id !== null ? (int) $this->purchase_order_line_id : null,
            'grn_id' => $this->grn_id !== null ? (int) $this->grn_id : null,
            'reason' => $this->reason,
            'description' => $this->description,
            'resolution_requested' => $this->resolution_requested,
            'defect_qty' => $this->defect_qty !== null ? (int) $this->defect_qty : null,
            'status' => $status,
            'review_outcome' => $this->review_outcome,
            'review_comment' => $this->review_comment,
            'reviewed_by' => $this->reviewed_by !== null ? (int) $this->reviewed_by : null,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'credit_note_id' => $this->credit_note_id !== null ? (int) $this->credit_note_id : null,
            'attachments' => DocumentResource::collection($this->whenLoaded('documents')),
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn () => PurchaseOrderResource::make($this->purchaseOrder)),
            'purchase_order_line' => $this->whenLoaded('purchaseOrderLine', fn () => PurchaseOrderLineResource::make($this->purchaseOrderLine)),
            'goods_receipt_note' => $this->whenLoaded('goodsReceiptNote', fn () => GoodsReceiptNoteResource::make($this->goodsReceiptNote)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
