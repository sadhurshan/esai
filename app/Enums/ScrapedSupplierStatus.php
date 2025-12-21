<?php

namespace App\Enums;

enum ScrapedSupplierStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Discarded = 'discarded';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Discarded], true);
    }
}
