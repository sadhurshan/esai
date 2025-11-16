<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\QuoteResource;

/** @mixin \App\Models\RFQ */
class RFQResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getRouteKey(),
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
            'items' => $this->when($this->relationLoaded('items'), function (): array {
                return $this->items->map(static fn ($item) => [
                    'id' => (string) $item->getRouteKey(),
                    'line_no' => $item->line_no,
                    'part_name' => $item->part_name,
                    'spec' => $item->spec,
                    'method' => $item->method,
                    'material' => $item->material,
                    'tolerance' => $item->tolerance,
                    'finish' => $item->finish,
                    'quantity' => $item->quantity,
                    'uom' => $item->uom,
                    'target_price' => $item->target_price,
                ])->all();
            }, []),
            'quotes' => $this->when($this->relationLoaded('quotes'), function () use ($request): array {
                return $this->quotes->map(function ($quote) use ($request) {
                    return (new QuoteResource($quote))->toArray($request);
                })->all();
            }, []),
        ];
    }
}
