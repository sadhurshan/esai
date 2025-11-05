<?php

namespace App\Enums;

enum CompanyStatus: string
{
    case Pending = 'pending';
    case PendingVerification = 'pending_verification';
    case Active = 'active';
    case Suspended = 'suspended';
    case Rejected = 'rejected';
}
