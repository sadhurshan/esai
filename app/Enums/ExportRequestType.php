<?php

namespace App\Enums;

enum ExportRequestType: string
{
    case FullData = 'full_data';
    case AuditLogs = 'audit_logs';
    case Custom = 'custom';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
