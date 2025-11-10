<?php

namespace App\Enums;

enum InventoryTxnType: string
{
    case Receive = 'receive';
    case Issue = 'issue';
    case AdjustIn = 'adjust_in';
    case AdjustOut = 'adjust_out';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case ReturnIn = 'return_in';
    case ReturnOut = 'return_out';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
