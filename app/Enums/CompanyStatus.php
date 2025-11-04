<?php

namespace App\Enums;

enum CompanyStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';
}
