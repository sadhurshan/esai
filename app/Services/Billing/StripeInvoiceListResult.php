<?php

namespace App\Services\Billing;

class StripeInvoiceListResult
{
    /**
     * @param  array<int, array<string, mixed>>  $invoices
     */
    public function __construct(
        public readonly bool $successful,
        public readonly array $invoices = [],
        public readonly ?string $message = null,
        public readonly ?string $code = null,
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>  $invoices
     */
    public static function success(array $invoices): self
    {
        return new self(true, $invoices);
    }

    public static function failure(string $code, string $message): self
    {
        return new self(false, [], $message, $code);
    }
}
