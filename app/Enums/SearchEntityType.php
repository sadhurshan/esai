<?php

namespace App\Enums;

enum SearchEntityType: string
{
    case Supplier = 'supplier';
    case Part = 'part';
    case RFQ = 'rfq';
    case PurchaseOrder = 'purchase_order';
    case Invoice = 'invoice';
    case Document = 'document';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
