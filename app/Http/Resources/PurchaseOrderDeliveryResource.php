<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PurchaseOrderDelivery */
class PurchaseOrderDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'purchase_order_id' => $this->purchase_order_id,
            'channel' => $this->channel,
            'status' => $this->status,
            'recipients_to' => $this->recipients_to,
            'recipients_cc' => $this->recipients_cc,
            'message' => $this->message,
            'delivery_reference' => $this->delivery_reference,
            'response_meta' => $this->response_meta,
            'error_reason' => $this->error_reason,
            'sent_at' => optional($this->sent_at ?? $this->created_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'sent_by' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->getKey(),
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),
        ];
    }
}
