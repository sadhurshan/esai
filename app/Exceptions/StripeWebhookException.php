<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class StripeWebhookException extends Exception
{
    public static function missingSecret(): self
    {
        return new self('Stripe webhook secret is not configured.');
    }

    public static function missingSignature(): self
    {
        return new self('Stripe signature header is missing.');
    }

    public static function invalidPayload(Throwable $previous): self
    {
        return new self('Unable to parse Stripe webhook payload.', 0, $previous);
    }

    public static function unexpectedEvent(string $expected, string $actual): self
    {
        return new self("Unexpected Stripe event type '{$actual}' when '{$expected}' was expected.");
    }
}
