<?php

namespace App\Enums;

enum DocumentCategory: string
{
    case Technical = 'technical';
    case Commercial = 'commercial';
    case Qa = 'qa';
    case Logistics = 'logistics';
    case Financial = 'financial';
    case Communication = 'communication';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
