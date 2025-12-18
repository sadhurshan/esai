<?php

namespace App\Actions\Dashboard;

use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RfqInvitation;
use App\Support\CompanyContext;

class ComputeSupplierDashboardMetricsAction
{
    /**
     * @return array{
     *     rfq_invitation_count:int,
     *     quotes_draft_count:int,
     *     quotes_submitted_count:int,
     *     purchase_orders_pending_ack_count:int,
     *     invoices_unpaid_count:int
     * }
     */
    public function execute(int $companyId, int $supplierId): array
    {
        return CompanyContext::forCompany($companyId, function () use ($supplierId): array {
            return [
                'rfq_invitation_count' => $this->countPendingInvitations($supplierId),
                'quotes_draft_count' => $this->countDraftQuotes($supplierId),
                'quotes_submitted_count' => $this->countSubmittedQuotes($supplierId),
                'purchase_orders_pending_ack_count' => $this->countPendingPurchaseOrders($supplierId),
                'invoices_unpaid_count' => $this->countUnpaidInvoices($supplierId),
            ];
        });
    }

    private function countPendingInvitations(int $supplierId): int
    {
        return RfqInvitation::query()
            ->where('supplier_id', $supplierId)
            ->where('status', RfqInvitation::STATUS_PENDING)
            ->count();
    }

    private function countDraftQuotes(int $supplierId): int
    {
        return Quote::query()
            ->where('supplier_id', $supplierId)
            ->where('status', 'draft')
            ->count();
    }

    private function countSubmittedQuotes(int $supplierId): int
    {
        return Quote::query()
            ->where('supplier_id', $supplierId)
            ->where('status', 'submitted')
            ->count();
    }

    private function countPendingPurchaseOrders(int $supplierId): int
    {
        return PurchaseOrder::query()
            ->where('supplier_id', $supplierId)
            ->where('ack_status', 'sent')
            ->count();
    }

    private function countUnpaidInvoices(int $supplierId): int
    {
        return Invoice::query()
            ->where('supplier_id', $supplierId)
            ->whereIn('status', ['pending', 'overdue', 'disputed'])
            ->count();
    }
}
