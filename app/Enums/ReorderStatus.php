<?php

namespace App\Enums;

enum ReorderStatus: string
{
    case Open = 'open';
    case Accepted = 'accepted';
    case Dismissed = 'dismissed';
    case Converted = 'converted';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
