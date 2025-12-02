<?php

namespace App\Services\Billing;

class BillingPortalSessionResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $portalUrl = null,
        public readonly ?string $code = null,
        public readonly ?string $message = null,
        public readonly ?string $fallbackUrl = null,
    ) {
    }

    public static function success(string $portalUrl): self
    {
        return new self(true, $portalUrl);
    }

    public static function failure(string $code, string $message, ?string $fallbackUrl = null): self
    {
        return new self(false, null, $code, $message, $fallbackUrl);
    }
}
