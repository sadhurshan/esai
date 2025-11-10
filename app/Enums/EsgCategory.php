<?php

namespace App\Enums;

enum EsgCategory: string
{
    case Policy = 'policy';
    case Certificate = 'certificate';
    case Emission = 'emission';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
