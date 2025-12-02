<?php

namespace App\Enums;

enum DownloadDocumentType: string
{
    case Rfq = 'rfq';
    case Quote = 'quote';
    case PurchaseOrder = 'purchase_order';
    case Invoice = 'invoice';
    case GoodsReceipt = 'grn';
    case CreditNote = 'credit_note';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type) => $type->value, self::cases());
    }
}
