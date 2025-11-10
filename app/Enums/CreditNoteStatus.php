<?php

namespace App\Enums;

enum CreditNoteStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Applied = 'applied';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
