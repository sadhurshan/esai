<?php

namespace App\Actions\PurchaseOrder;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

class HandleSupplierAcknowledgementAction
{
    public function __construct(
        private readonly RecordPurchaseOrderEventAction $recordEvent,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(User $user, PurchaseOrder $purchaseOrder, string $decision, ?string $reason = null): PurchaseOrder
    {
        $purchaseOrder->loadMissing('quote.supplier');

        if ($user->company_id === null) {
            throw ValidationException::withMessages([
                'company_id' => ['Supplier context missing.'],
            ]);
        }

        $supplierCompanyId = $purchaseOrder->quote?->supplier?->company_id;

        if ($supplierCompanyId === null || (int) $supplierCompanyId !== (int) $user->company_id) {
            throw ValidationException::withMessages([
                'purchase_order_id' => ['You are not authorized to act on this purchase order.'],
            ]);
        }

        if ($purchaseOrder->ack_status !== 'sent') {
            throw ValidationException::withMessages([
                'ack_status' => ['Only sent purchase orders can be acknowledged or declined.'],
            ]);
        }

        $before = $purchaseOrder->getOriginal();

        if ($decision === 'acknowledged') {
            $purchaseOrder->ack_status = 'acknowledged';
            $purchaseOrder->status = 'acknowledged';
            $purchaseOrder->ack_reason = null;
        } else {
            $purchaseOrder->ack_status = 'declined';
            $purchaseOrder->status = 'cancelled';
            $purchaseOrder->ack_reason = $reason;
        }

        $purchaseOrder->acknowledged_at = now();
        $purchaseOrder->save();

        $this->auditLogger->updated($purchaseOrder, $before, [
            'ack_status' => $purchaseOrder->ack_status,
            'status' => $purchaseOrder->status,
            'ack_reason' => $purchaseOrder->ack_reason,
            'acknowledged_at' => $purchaseOrder->acknowledged_at,
        ]);

        $this->recordEvent->execute(
            $purchaseOrder,
            $decision === 'acknowledged' ? 'supplier_ack' : 'supplier_decline',
            $decision === 'acknowledged' ? 'Supplier acknowledged the PO' : 'Supplier declined the PO',
            $reason,
            [
                'decision' => $decision,
                'reason' => $reason,
            ],
            $user,
            $purchaseOrder->acknowledged_at,
        );

        return $purchaseOrder->fresh(['lines.taxes.taxCode', 'quote.supplier']);
    }
}
