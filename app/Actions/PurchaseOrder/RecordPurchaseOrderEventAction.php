<?php

namespace App\Actions\PurchaseOrder;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

class RecordPurchaseOrderEventAction
{
    public function execute(
        PurchaseOrder $purchaseOrder,
        string $eventType,
        string $summary,
        ?string $description = null,
        array $meta = [],
        ?User $actor = null,
        ?Carbon $occurredAt = null,
    ): PurchaseOrderEvent {
        $timestamp = $occurredAt ?? now();

        return PurchaseOrderEvent::create([
            'purchase_order_id' => $purchaseOrder->getKey(),
            'event_type' => $eventType,
            'summary' => $summary,
            'description' => $description,
            'meta' => $meta ?: null,
            'actor_id' => $actor?->getKey(),
            'actor_name' => $actor?->name,
            'actor_type' => $this->resolveActorType($actor),
            'occurred_at' => $timestamp,
        ]);
    }

    private function resolveActorType(?User $actor): ?string
    {
        if ($actor === null) {
            return 'system';
        }

        return str_starts_with((string) $actor->role, 'supplier') ? 'supplier' : 'buyer';
    }
}
