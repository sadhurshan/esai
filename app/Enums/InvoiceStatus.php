<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case BuyerReview = 'buyer_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }

    public function isSupplierEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }

    public function isBuyerEditable(): bool
    {
        return in_array($this, [self::Draft, self::BuyerReview], true);
    }
}
