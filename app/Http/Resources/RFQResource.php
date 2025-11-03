<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RFQ */
class RFQResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'item_name' => $this->item_name,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'material' => $this->material,
            'method' => $this->method,
            'tolerance' => $this->tolerance,
            'finish' => $this->finish,
            'client_company' => $this->client_company,
            'status' => $this->status,
            'deadline_at' => optional($this->deadline_at)?->toIso8601String(),
            'sent_at' => optional($this->sent_at)?->toIso8601String(),
            'is_open_bidding' => (bool) $this->is_open_bidding,
            'notes' => $this->notes,
            'cad_path' => $this->cad_path,
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'quotes' => $this->when($this->relationLoaded('quotes'), function () use ($request): array {
                return $this->quotes->map(function ($quote) use ($request) {
                    $quote->setRelation('rfq', $this->resource);

                    return (new RFQQuoteResource($quote))->toArray($request);
                })->all();
            }, []),
        ];
    }
}
