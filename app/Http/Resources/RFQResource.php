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
        $rawMeta = $this->meta === null
            ? null
            : (is_array($this->meta) ? $this->meta : (array) $this->meta);
        $paymentTerms = is_array($rawMeta) ? ($rawMeta['payment_terms'] ?? null) : null;
        $taxPercent = is_array($rawMeta) ? ($rawMeta['tax_percent'] ?? null) : null;

        return [
            'id' => (string) $this->getRouteKey(),
            'company_id' => (string) $this->company_id,
            'number' => $this->number,
            'title' => $this->title,
            'item_name' => $this->title,
            'method' => $this->method,
            'material' => $this->material,
            'tolerance' => $this->tolerance,
            'finish' => $this->finish,
            'quantity_total' => $this->quantity_total,
            'quantity' => $this->quantity_total,
            'delivery_location' => $this->delivery_location,
            'client_company' => $this->delivery_location,
            'incoterm' => $this->incoterm,
            'currency' => $this->currency,
            'payment_terms' => $paymentTerms,
            'paymentTerms' => $paymentTerms,
            'tax_percent' => $taxPercent,
            'taxPercent' => $taxPercent,
            'notes' => $this->notes,
            'open_bidding' => (bool) $this->open_bidding,
            'is_open_bidding' => (bool) $this->open_bidding,
            'status' => $this->status,
            'publish_at' => optional($this->publish_at)?->toIso8601String(),
            'sent_at' => optional($this->publish_at)?->toIso8601String(),
            'due_at' => optional($this->due_at)?->toIso8601String(),
            'deadline_at' => optional($this->due_at)?->toIso8601String(),
            'close_at' => optional($this->close_at)?->toIso8601String(),
            'rfq_version' => $this->rfq_version,
            'attachments_count' => $this->attachments_count,
            'meta' => $rawMeta === null ? null : (object) $rawMeta,
            'cad_document_id' => $this->cad_document_id !== null ? (string) $this->cad_document_id : null,
            'cad_document' => $this->whenLoaded('cadDocument', function () use ($request) {
                return (new DocumentResource($this->cadDocument))->toArray($request);
            }),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'items' => $this->when($this->relationLoaded('items'), function (): array {
                return $this->items->map(static fn ($item) => [
                    'id' => (string) $item->getRouteKey(),
                    'line_no' => $item->line_no,
                    'part_number' => $item->part_number ?? $item->part_name,
                    'description' => $item->description ?? $item->spec,
                    'method' => $item->method,
                    'material' => $item->material,
                    'tolerance' => $item->tolerance,
                    'finish' => $item->finish,
                    'qty' => $item->qty ?? $item->quantity,
                    'uom' => $item->uom,
                    'target_price' => $item->target_price,
                    'cad_doc_id' => $item->cad_doc_id !== null ? (string) $item->cad_doc_id : null,
                    'specs_json' => $item->specs_json ?? $item->spec,
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
