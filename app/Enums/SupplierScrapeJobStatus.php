<?php

namespace App\Enums;

enum SupplierScrapeJobStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }
}
