<?php

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Invoice */
class InvoiceResource extends JsonResource
{
    private const STATUS_MAP = [
        'draft' => 'draft',
        'paid' => 'paid',
        'approved' => 'approved',
        'submitted' => 'submitted',
        'buyer_review' => 'buyer_review',
        'rejected' => 'rejected',
        'pending' => 'pending',
        'overdue' => 'overdue',
        'disputed' => 'disputed',
        'posted' => 'posted',
    ];

    public function toArray(Request $request): array
    {
        $matchSummary = $this->buildMatchSummary();

        return [
            'id' => (string) $this->getRouteKey(),
            'company_id' => $this->company_id,
            'purchase_order_id' => $this->purchase_order_id,
            'supplier_id' => $this->supplier_id,
            'supplier_company_id' => $this->supplier_company_id,
            'invoice_number' => $this->invoice_number,
            'invoice_date' => optional($this->invoice_date)?->toDateString(),
            'due_date' => optional($this->due_date)?->toDateString(),
            'currency' => $this->currency,
            'status' => self::STATUS_MAP[$this->status] ?? $this->status,
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'total' => (float) $this->total,
            'subtotal_minor' => $this->subtotal_minor,
            'tax_minor' => $this->tax_minor,
            'total_minor' => $this->total_minor,
            'matched_status' => $this->matched_status,
            'created_by_type' => $this->created_by_type,
            'created_by_id' => $this->created_by_id,
            'submitted_at' => optional($this->submitted_at)?->toIso8601String(),
            'reviewed_at' => optional($this->reviewed_at)?->toIso8601String(),
            'reviewed_by_id' => $this->reviewed_by_id,
            'review_note' => $this->review_note,
            'payment_reference' => $this->payment_reference,
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier?->getKey(),
                'name' => $this->supplier?->name,
            ]),
            'supplier_company' => $this->whenLoaded('supplierCompany', fn () => [
                'id' => $this->supplierCompany?->getKey(),
                'name' => $this->supplierCompany?->name,
            ]),
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn () => [
                'id' => $this->purchaseOrder?->getKey(),
                'po_number' => $this->purchaseOrder?->po_number,
            ]),
            'reviewed_by' => $this->whenLoaded('reviewedBy', fn () => [
                'id' => $this->reviewedBy?->getKey(),
                'name' => $this->reviewedBy?->name,
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
            'payments' => $this->whenLoaded('payments', fn () => $this->payments
                ->map(fn ($payment) => (new InvoicePaymentResource($payment))->toArray($request))
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
