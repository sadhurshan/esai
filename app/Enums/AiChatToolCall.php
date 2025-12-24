<?php

namespace App\Enums;

enum AiChatToolCall: string
{
    case SearchRfqs = 'workspace.search_rfqs';
    case GetRfq = 'workspace.get_rfq';
    case ListSuppliers = 'workspace.list_suppliers';
    case GetQuotesForRfq = 'workspace.get_quotes_for_rfq';
    case GetInventoryItem = 'workspace.get_inventory_item';
    case LowStock = 'workspace.low_stock';
    case GetAwards = 'workspace.get_awards';
    case QuoteStats = 'workspace.stats_quotes';
    case GetReceipts = 'workspace.get_receipts';
    case GetInvoices = 'workspace.get_invoices';
    case AwardQuote = 'workspace.award_quote';
    case InvoiceDraft = 'workspace.invoice_draft';
    case ApproveInvoice = 'workspace.approve_invoice';
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
