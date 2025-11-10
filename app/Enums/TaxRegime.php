<?php

namespace App\Enums;

enum TaxRegime: string
{
    case Exclusive = 'exclusive';
    case Inclusive = 'inclusive';
}
