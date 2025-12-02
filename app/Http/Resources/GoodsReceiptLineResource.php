<?php

namespace App\Http\Resources;

use App\Models\Document;
use App\Models\PurchaseOrderLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\GoodsReceiptLine */
class GoodsReceiptLineResource extends JsonResource
{
    /**
     * @param array<int, Document> $attachmentMap
     */
    public function __construct($resource, private readonly array $attachmentMap = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        /** @var PurchaseOrderLine|null $poLine */
        $poLine = $this->resource->relationLoaded('purchaseOrderLine') ? $this->purchaseOrderLine : null;

        $orderedQty = $poLine?->quantity !== null ? (float) $poLine->quantity : null;
        $totalReceived = $poLine?->received_qty !== null ? (float) $poLine->received_qty : null;
        $previouslyReceived = $totalReceived !== null ? max(0, $totalReceived - (float) ($this->received_qty ?? 0)) : null;
        $remaining = null;

        if ($orderedQty !== null && $totalReceived !== null) {
            $remaining = max(0, $orderedQty - $totalReceived);
        }

        $attachmentIds = collect($this->attachment_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter();

        $attachments = $attachmentIds
            ->map(function (int $id) use ($request) {
                $document = $this->attachmentMap[$id] ?? null;

                if ($document === null) {
                    return null;
                }

                return (new DocumentResource($document))->toArray($request);
            })
            ->filter()
            ->values()
            ->all();

        $ncrs = $this->whenLoaded('ncrs', function () use ($request) {
            return $this->ncrs
                ->map(fn ($ncr) => (new NcrResource($ncr))->toArray($request))
                ->values()
                ->all();
        });

        $openNcrCount = $this->whenLoaded('ncrs', function () {
            return $this->ncrs->where('status', 'open')->count();
        });

        return [
            'id' => $this->getKey(),
            'goods_receipt_note_id' => $this->goods_receipt_note_id,
            'purchase_order_line_id' => $this->purchase_order_line_id,
            'po_line_id' => $this->purchase_order_line_id,
            'line_no' => $poLine?->line_no,
            'description' => $poLine?->description,
            'ordered_qty' => $orderedQty,
            'received_qty' => $this->received_qty !== null ? (float) $this->received_qty : null,
            'accepted_qty' => $this->accepted_qty !== null ? (float) $this->accepted_qty : null,
            'rejected_qty' => $this->rejected_qty !== null ? (float) $this->rejected_qty : null,
            'previously_received' => $previouslyReceived,
            'remaining_qty' => $remaining,
            'defect_notes' => $this->defect_notes,
            'notes' => $this->defect_notes,
            'uom' => $poLine?->uom,
            'unit_price_minor' => $poLine?->unit_price_minor,
            'currency' => $poLine?->currency,
            'variance' => null,
            'attachments' => $attachments,
            'ncr_flag' => (bool) $this->ncr_flag,
            'open_ncr_count' => $openNcrCount,
            'ncrs' => $ncrs,
        ];
    }
}
