<?php

namespace App\Enums;

enum CompanySupplierStatus: string
{
    case None = 'none';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
