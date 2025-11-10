<?php

namespace App\Enums;

enum RfqClarificationType: string
{
    case Question = 'question';
    case Answer = 'answer';
    case Amendment = 'amendment';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
