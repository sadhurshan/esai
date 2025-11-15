<?php

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\GoodsReceiptNote */
class GoodsReceiptNoteResource extends JsonResource
{
    private const STATUS_MAP = [
        'pending' => 'draft',
        'draft' => 'draft',
        'inspecting' => 'inspecting',
        'complete' => 'posted',
        'accepted' => 'posted',
        'ncr_raised' => 'variance',
        'rejected' => 'rejected',
    ];

    /**
     * @param array<int, Document> $attachmentMap
     */
    public function __construct($resource, private readonly array $attachmentMap = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $purchaseOrder = $this->resource->relationLoaded('purchaseOrder') ? $this->purchaseOrder : null;
        $supplier = $purchaseOrder && $purchaseOrder->relationLoaded('supplier') ? $purchaseOrder->supplier : null;
        $attachments = $this->serializeAttachments();

        return [
            'id' => $this->getKey(),
            'company_id' => $this->company_id,
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order_number' => $purchaseOrder?->po_number,
            'po_number' => $purchaseOrder?->po_number,
            'grn_number' => $this->number,
            'number' => $this->number,
            'status' => self::STATUS_MAP[$this->status] ?? $this->status,
            'inspected_by_id' => $this->inspected_by_id,
            'inspected_at' => optional($this->inspected_at)?->toIso8601String(),
            'received_at' => optional($this->inspected_at)?->toIso8601String(),
            'posted_at' => optional($this->created_at)?->toIso8601String(),
            'reference' => $this->reference,
            'notes' => $this->notes,
            'supplier_id' => $supplier?->id,
            'supplier_name' => $supplier?->name,
            'inspector' => $this->whenLoaded('inspector', fn () => [
                'id' => $this->inspector?->id,
                'name' => $this->inspector?->name,
            ]),
            'created_by' => $this->whenLoaded('inspector', fn () => [
                'id' => $this->inspector?->id,
                'name' => $this->inspector?->name,
            ]),
            'lines_count' => $this->lines_count ?? ($this->relationLoaded('lines') ? $this->lines->count() : null),
            'attachments_count' => $this->attachments_count ?? count($attachments),
            'lines' => $this->when($this->relationLoaded('lines'), function () use ($request) {
                return $this->lines->map(function ($line) use ($request) {
                    return (new GoodsReceiptLineResource($line, $this->attachmentMap))->toArray($request);
                })->values()->all();
            }, []),
            'attachments' => $attachments,
            'timeline' => [],
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeAttachments(): array
    {
        $fromLines = collect($this->attachmentMap)
            ->filter()
            ->map(function (Document $document): array {
                return [
                    'id' => (string) $document->getRouteKey(),
                    'filename' => $document->filename,
                    'mime' => $document->mime,
                    'size_bytes' => (int) $document->size_bytes,
                    'created_at' => optional($document->created_at)?->toIso8601String(),
                ];
            });

        $direct = $this->resource->relationLoaded('attachments')
            ? $this->attachments->map(function (Document $document): array {
                return [
                    'id' => (string) $document->getRouteKey(),
                    'filename' => $document->filename,
                    'mime' => $document->mime,
                    'size_bytes' => (int) $document->size_bytes,
                    'created_at' => optional($document->created_at)?->toIso8601String(),
                ];
            })
            : collect();

        return $direct
            ->concat($fromLines)
            ->unique('id')
            ->values()
            ->all();
    }
}
