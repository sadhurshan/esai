<?php

namespace App\Http\Resources;

use App\Models\Document;
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
        $attachmentIds = collect($this->attachment_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter();

        $attachments = $attachmentIds
            ->map(function (int $id) {
                $document = $this->attachmentMap[$id] ?? null;

                if ($document === null) {
                    return null;
                }

                return [
                    'id' => (string) $document->getRouteKey(),
                    'filename' => $document->filename,
                    'mime' => $document->mime,
                    'size_bytes' => $document->size_bytes,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'id' => $this->getKey(),
            'goods_receipt_note_id' => $this->goods_receipt_note_id,
            'purchase_order_line_id' => $this->purchase_order_line_id,
            'received_qty' => $this->received_qty !== null ? (float) $this->received_qty : null,
            'accepted_qty' => $this->accepted_qty !== null ? (float) $this->accepted_qty : null,
            'rejected_qty' => $this->rejected_qty !== null ? (float) $this->rejected_qty : null,
            'defect_notes' => $this->defect_notes,
            'attachments' => $attachments,
        ];
    }
}
