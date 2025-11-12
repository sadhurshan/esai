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
        'complete' => 'accepted',
        'accepted' => 'accepted',
        'ncr_raised' => 'ncr_raised',
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
        return [
            'id' => $this->getKey(),
            'company_id' => $this->company_id,
            'purchase_order_id' => $this->purchase_order_id,
            'number' => $this->number,
            'status' => self::STATUS_MAP[$this->status] ?? $this->status,
            'inspected_by_id' => $this->inspected_by_id,
            'inspected_at' => optional($this->inspected_at)?->toIso8601String(),
            'inspector' => $this->whenLoaded('inspector', fn () => [
                'id' => $this->inspector?->id,
                'name' => $this->inspector?->name,
            ]),
            'lines' => $this->when($this->relationLoaded('lines'), function () use ($request) {
                return $this->lines->map(function ($line) use ($request) {
                    return (new GoodsReceiptLineResource($line, $this->attachmentMap))->toArray($request);
                })->values()->all();
            }, []),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
