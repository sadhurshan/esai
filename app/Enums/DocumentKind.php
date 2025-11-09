<?php

namespace App\Enums;

enum DocumentKind: string
{
    case Rfq = 'rfq';
    case Quote = 'quote';
    case PurchaseOrder = 'po';
    case Invoice = 'invoice';
    case Supplier = 'supplier';
    case Part = 'part';
    case Cad = 'cad';
    case Manual = 'manual';
    case Certificate = 'certificate';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
