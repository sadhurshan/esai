<?php

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Invoice */
class InvoiceResource extends JsonResource
{
    private const STATUS_MAP = [
        'pending' => 'pending',
        'draft' => 'draft',
        'paid' => 'paid',
        'approved' => 'approved',
        'submitted' => 'submitted',
        'overdue' => 'overdue',
        'disputed' => 'disputed',
        'posted' => 'posted',
        'rejected' => 'rejected',
    ];

    public function toArray(Request $request): array
    {
        $matchSummary = $this->buildMatchSummary();

        return [
            'id' => (string) $this->getRouteKey(),
            'company_id' => $this->company_id,
            'purchase_order_id' => $this->purchase_order_id,
            'supplier_id' => $this->supplier_id,
            'invoice_number' => $this->invoice_number,
            'invoice_date' => optional($this->invoice_date)?->toDateString(),
            'currency' => $this->currency,
            'status' => self::STATUS_MAP[$this->status] ?? $this->status,
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'total' => (float) $this->total,
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier?->getKey(),
                'name' => $this->supplier?->name,
            ]),
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn () => [
                'id' => $this->purchaseOrder?->getKey(),
                'po_number' => $this->purchaseOrder?->po_number,
            ]),
            'lines' => $this->when($this->relationLoaded('lines'), fn () => $this->lines
                ->map(fn ($line) => (new InvoiceLineResource($line))->toArray($request))
                ->values()
                ->all(), []),
            'matches' => $this->when($this->relationLoaded('matches'), fn () => $this->matches
                ->map(fn ($match) => (new InvoiceMatchResource($match))->toArray($request))
                ->values()
                ->all(), []),
            'match_summary' => $matchSummary,
            'document' => $this->whenLoaded('document', fn () => $this->formatDocument($this->document)),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments
                ->map(fn ($attachment) => (new DocumentResource($attachment))->toArray($request))
                ->values()
                ->all(), []),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function buildMatchSummary(): array
    {
        $summary = [
            'matched' => 0,
            'qty_mismatch' => 0,
            'price_mismatch' => 0,
            'unmatched' => 0,
        ];

        if (! $this->relationLoaded('matches')) {
            return $summary;
        }

        foreach ($this->matches as $match) {
            $key = $match->result;
            if (array_key_exists($key, $summary)) {
                $summary[$key]++;
            }
        }

        return $summary;
    }

    private function formatDocument(?Document $document): ?array
    {
        if ($document === null) {
            return null;
        }

        return [
            'id' => $document->id,
            'filename' => $document->filename,
            'mime' => $document->mime,
            'size_bytes' => $document->size_bytes,
            'created_at' => optional($document->created_at)?->toIso8601String(),
        ];
    }
}
