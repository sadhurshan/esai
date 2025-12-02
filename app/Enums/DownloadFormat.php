<?php

namespace App\Enums;

enum DownloadFormat: string
{
    case Pdf = 'pdf';
    case Csv = 'csv';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $format) => $format->value, self::cases());
    }
}
