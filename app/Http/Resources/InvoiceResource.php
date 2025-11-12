<?php

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Invoice */
class InvoiceResource extends JsonResource
{
    private const STATUS_MAP = [
        'pending' => 'draft',
        'draft' => 'draft',
        'paid' => 'paid',
        'approved' => 'approved',
        'submitted' => 'submitted',
        'overdue' => 'rejected',
        'disputed' => 'rejected',
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
            'currency' => $this->currency,
            'status' => self::STATUS_MAP[$this->status] ?? $this->status,
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'total' => (float) $this->total,
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
