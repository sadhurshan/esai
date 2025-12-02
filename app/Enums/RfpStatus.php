<?php

namespace App\Enums;

enum RfpStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case InReview = 'in_review';
    case Awarded = 'awarded';
    case NoAward = 'no_award';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
