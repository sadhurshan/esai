<?php

namespace App\Services\Billing;

use App\Models\Company;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeInvoiceService
{
    public function listRecentInvoices(Company $company, ?int $limit = null): StripeInvoiceListResult
    {
        $secret = config('services.stripe.secret');

        if (blank($secret)) {
            return StripeInvoiceListResult::failure('missing_secret', 'Stripe secret is not configured.');
        }

        if (blank($company->stripe_id)) {
            return StripeInvoiceListResult::failure('customer_missing', 'Company is not linked to a Stripe customer.');
        }

        $configuredLimit = (int) config('services.stripe.invoice_history_limit', 12);
        $limit = $limit ?? $configuredLimit;
        $limit = max(1, min($limit, 100));

        $response = Http::withBasicAuth($secret, '')
            ->get('https://api.stripe.com/v1/invoices', [
                'customer' => $company->stripe_id,
                'limit' => $limit,
            ]);

        if ($response->failed()) {
            Log::warning('Failed to load Stripe invoices', [
                'company_id' => $company->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return StripeInvoiceListResult::failure('provider_error', 'Stripe did not return invoice history.');
        }

        $items = $response->json('data');

        if (! is_array($items)) {
            return StripeInvoiceListResult::failure('provider_error', 'Stripe returned an unexpected invoice payload.');
        }

        $invoices = collect($items)
            ->filter(static fn ($invoice): bool => is_array($invoice))
            ->map(fn (array $invoice): array => $this->transformInvoice($invoice))
            ->values()
            ->all();

        return StripeInvoiceListResult::success($invoices);
    }

    /**
     * @param  array<string, mixed>  $invoice
     * @return array<string, mixed>
     */
    private function transformInvoice(array $invoice): array
    {
        $currency = strtoupper((string) ($invoice['currency'] ?? 'USD'));

        return [
            'id' => $invoice['id'] ?? null,
            'number' => $invoice['number'] ?? null,
            'status' => $invoice['status'] ?? null,
            'currency' => $currency,
            'amount_due' => $this->normalizeInteger($invoice['amount_due'] ?? null),
            'amount_paid' => $this->normalizeInteger($invoice['amount_paid'] ?? null),
            'amount_remaining' => $this->normalizeInteger($invoice['amount_remaining'] ?? null),
            'total' => $this->normalizeInteger($invoice['total'] ?? null),
            'hosted_invoice_url' => $invoice['hosted_invoice_url'] ?? null,
            'invoice_pdf' => $invoice['invoice_pdf'] ?? null,
            'created_at' => $this->normalizeTimestamp($invoice['created'] ?? null),
            'due_at' => $this->normalizeTimestamp($invoice['due_date'] ?? null),
            'period_start' => $this->normalizeTimestamp($invoice['period_start'] ?? null),
            'period_end' => $this->normalizeTimestamp($invoice['period_end'] ?? null),
            'collection_method' => $invoice['collection_method'] ?? null,
            'attempt_count' => $this->normalizeInteger($invoice['attempt_count'] ?? null),
            'next_payment_attempt_at' => $this->normalizeTimestamp($invoice['next_payment_attempt'] ?? null),
            'is_paid' => (bool) ($invoice['paid'] ?? false),
            'is_attempting_collection' => (bool) ($invoice['attempting_collection'] ?? false),
        ];
    }

    private function normalizeInteger(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        try {
            return Carbon::createFromTimestamp((int) $value)->toISOString();
        } catch (\Throwable) {
            return null;
        }
    }
}
