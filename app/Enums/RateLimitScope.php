<?php

namespace App\Enums;

enum RateLimitScope: string
{
    case Api = 'api';
    case WebhookOut = 'webhook_out';
    case Emails = 'emails';
}
