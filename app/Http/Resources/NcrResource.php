<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Ncr */
class NcrResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $documents = collect($this->documents_json ?? [])
            ->map(function ($documentId): array {
                return [
                    'id' => (int) $documentId,
                ];
            })
            ->values()
            ->all();

        return [
            'id' => (int) $this->getKey(),
            'company_id' => (int) $this->company_id,
            'grn_id' => (int) $this->goods_receipt_note_id,
            'po_line_id' => (int) $this->purchase_order_line_id,
            'status' => $this->status,
            'disposition' => $this->disposition,
            'reason' => $this->reason,
            'attachments' => $documents,
            'raised_by' => $this->whenLoaded('raisedBy', fn () => [
                'id' => $this->raisedBy?->id,
                'name' => $this->raisedBy?->name,
            ]),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
