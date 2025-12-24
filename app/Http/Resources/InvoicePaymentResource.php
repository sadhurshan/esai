<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\InvoicePayment */
class InvoicePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getRouteKey(),
            'invoice_id' => $this->invoice_id,
            'amount' => (float) $this->amount,
            'amount_minor' => $this->amount_minor,
            'currency' => $this->currency,
            'paid_at' => optional($this->paid_at)?->toIso8601String(),
            'payment_reference' => $this->payment_reference,
            'payment_method' => $this->payment_method,
            'note' => $this->note,
            'created_by' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->getKey(),
                'name' => $this->creator?->name,
            ]),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
