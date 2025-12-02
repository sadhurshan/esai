<?php

namespace App\Enums;

enum WebhookDeliveryStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case DeadLettered = 'dead_letter';
}
