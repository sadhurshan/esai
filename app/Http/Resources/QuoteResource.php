<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Quote */
class QuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rfq_id' => $this->rfq_id,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier?->id,
                'name' => $this->supplier?->name,
            ]),
            'currency' => $this->currency,
            'unit_price' => (float) $this->unit_price,
            'min_order_qty' => $this->min_order_qty,
            'lead_time_days' => $this->lead_time_days,
            'note' => $this->note,
            'status' => $this->status,
            'revision_no' => $this->revision_no,
            'submitted_by' => $this->submitted_by,
            'submitted_at' => optional($this->created_at)?->toIso8601String(),
            'items' => QuoteItemResource::collection($this->whenLoaded('items')),
            'attachments' => $this->whenLoaded('documents', fn () => $this->documents->map(fn ($document) => [
                'id' => $document->id,
                'filename' => $document->filename,
                'path' => $document->path,
                'mime' => $document->mime,
                'size_bytes' => $document->size_bytes,
            ])->all()),
        ];
    }
}
