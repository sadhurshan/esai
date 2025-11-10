<?php

namespace App\Enums;

enum ReorderMethod: string
{
    case Minmax = 'minmax';
    case Sma = 'sma';
    case Ema = 'ema';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
