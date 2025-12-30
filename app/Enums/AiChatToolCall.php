<?php

namespace App\Enums;

enum AiChatToolCall: string
{
    case SearchRfqs = 'workspace.search_rfqs';
    case SearchPos = 'workspace.search_pos';
    case SearchReceipts = 'workspace.search_receipts';
    case SearchInvoices = 'workspace.search_invoices';
    case SearchPayments = 'workspace.search_payments';
    case SearchContracts = 'workspace.search_contracts';
    case SearchItems = 'workspace.search_items';
    case SearchSuppliers = 'workspace.search_suppliers';
    case SearchDisputes = 'workspace.search_disputes';
    case Navigate = 'workspace.navigate';
    case NextBestAction = 'workspace.next_best_action';
    case GetRfq = 'workspace.get_rfq';
    case GetPo = 'workspace.get_po';
    case GetReceipt = 'workspace.get_receipt';
    case GetReceipts = 'workspace.get_receipts';
    case GetInvoice = 'workspace.get_invoice';
    case GetInvoices = 'workspace.get_invoices';
    case GetPayment = 'workspace.get_payment';
    case GetContract = 'workspace.get_contract';
    case GetItem = 'workspace.get_item';
    case GetSupplier = 'workspace.get_supplier';
    case GetDispute = 'workspace.get_dispute';
    case ListSuppliers = 'workspace.list_suppliers';
    case SupplierRiskSnapshot = 'workspace.supplier_risk_snapshot';
    case PolicyCheck = 'workspace.policy_check';
    case GetQuotesForRfq = 'workspace.get_quotes_for_rfq';
    case GetInventoryItem = 'workspace.get_inventory_item';
    case LowStock = 'workspace.low_stock';
    case GetAwards = 'workspace.get_awards';
    case QuoteStats = 'workspace.stats_quotes';
    case ProcurementSnapshot = 'workspace.procurement_snapshot';
    case AwardQuote = 'workspace.award_quote';
    case InvoiceDraft = 'workspace.invoice_draft';
    case CreateDisputeDraft = 'workspace.create_dispute_draft';
    case ResolveInvoiceMismatch = 'workspace.resolve_invoice_mismatch';
    case ApproveInvoice = 'workspace.approve_invoice';
    case RequestApproval = 'workspace.request_approval';
    case Help = 'workspace.help';
    case ReviewRfq = 'workspace.review_rfq';
    case ReviewQuote = 'workspace.review_quote';
    case ReviewPo = 'workspace.review_po';
    case ReviewInvoice = 'workspace.review_invoice';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $call) => $call->value, self::cases());
    }
}
