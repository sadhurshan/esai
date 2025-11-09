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
                    'id' => $document->id,
                    'filename' => $document->filename,
                    'kind' => $document->kind,
                    'mime' => $document->mime,
                    'size_bytes' => $document->size_bytes,
                    'created_at' => optional($document->created_at)?->toIso8601String(),
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'id' => $this->id,
            'goods_receipt_note_id' => $this->goods_receipt_note_id,
            'purchase_order_line_id' => $this->purchase_order_line_id,
            'received_qty' => (int) $this->received_qty,
            'accepted_qty' => (int) $this->accepted_qty,
            'rejected_qty' => (int) $this->rejected_qty,
            'defect_notes' => $this->defect_notes,
            'attachment_ids' => $attachmentIds->values()->all(),
            'attachments' => $attachments,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
