<?php

namespace App\Enums;

enum DocumentNumberType: string
{
    case RFQ = 'rfq';
    case Quote = 'quote';
    case PO = 'po';
    case Invoice = 'invoice';
    case GRN = 'grn';
    case Credit = 'credit';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
