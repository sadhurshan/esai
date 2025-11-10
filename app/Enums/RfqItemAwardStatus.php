<?php

namespace App\Enums;

enum RfqItemAwardStatus: string
{
    case Awarded = 'awarded';
    case Cancelled = 'cancelled';
}
