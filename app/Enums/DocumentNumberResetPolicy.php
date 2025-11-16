<?php

namespace App\Enums;

enum DocumentNumberResetPolicy: string
{
    case Never = 'never';
    case Yearly = 'yearly';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
