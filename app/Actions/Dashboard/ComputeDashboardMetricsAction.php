<?php

namespace App\Actions\Dashboard;

use App\Enums\InvoiceStatus;
use App\Enums\ReorderStatus;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\ReorderSuggestion;

class ComputeDashboardMetricsAction
{
    /**
     * @return array{open_rfq_count: int, quotes_awaiting_review_count: int, pos_awaiting_acknowledgement_count: int, unpaid_invoice_count: int, low_stock_part_count: int}
     */
    public function execute(int $companyId): array
    {
        return [
            'open_rfq_count' => $this->countOpenRfqs($companyId),
            'quotes_awaiting_review_count' => $this->countQuotesAwaitingReview($companyId),
            'pos_awaiting_acknowledgement_count' => $this->countPurchaseOrdersAwaitingAcknowledgement($companyId),
            'unpaid_invoice_count' => $this->countUnpaidInvoices($companyId),
            'low_stock_part_count' => $this->countLowStockParts($companyId),
        ];
    }

    private function countOpenRfqs(int $companyId): int
    {
        return RFQ::query()
            ->where('company_id', $companyId)
            ->where('status', 'open')
            ->count();
    }

    private function countQuotesAwaitingReview(int $companyId): int
    {
        return Quote::query()
            ->where('company_id', $companyId)
            ->where('status', 'submitted')
            ->count();
    }

    private function countPurchaseOrdersAwaitingAcknowledgement(int $companyId): int
    {
        return PurchaseOrder::query()
            ->where('company_id', $companyId)
            ->where('status', 'sent')
            ->count();
    }

    private function countUnpaidInvoices(int $companyId): int
    {
        $unpaidStatuses = [
            InvoiceStatus::Draft->value,
            InvoiceStatus::Submitted->value,
            InvoiceStatus::BuyerReview->value,
            InvoiceStatus::Approved->value,
        ];

        return Invoice::query()
            ->where('company_id', $companyId)
            ->whereIn('status', $unpaidStatuses)
            ->count();
    }

    private function countLowStockParts(int $companyId): int
    {
        return ReorderSuggestion::query()
            ->where('company_id', $companyId)
            ->where('status', ReorderStatus::Open)
            ->count();
    }
}
