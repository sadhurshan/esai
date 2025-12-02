<?php

namespace App\Services\Billing;

use App\Models\Company;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeBillingPortalService
{
    public function __construct(private readonly UrlGenerator $url)
    {
    }

    public function createPortalSession(Company $company): BillingPortalSessionResult
    {
        $secret = config('services.stripe.secret');

        if (blank($secret)) {
            return BillingPortalSessionResult::failure('missing_secret', 'Stripe secret is not configured.', $this->fallbackUrl());
        }

        if (blank($company->stripe_id)) {
            return BillingPortalSessionResult::failure('customer_missing', 'Company is not linked to a Stripe customer.', $this->fallbackUrl());
        }

        $payload = [
            'customer' => $company->stripe_id,
            'return_url' => $this->portalReturnUrl(),
        ];

        if ($configuration = config('services.stripe.portal_configuration')) {
            $payload['configuration'] = $configuration;
        }

        $response = Http::withBasicAuth($secret, '')
            ->asForm()
            ->post('https://api.stripe.com/v1/billing_portal/sessions', $payload);

        if ($response->failed()) {
            Log::warning('Failed to create Stripe billing portal session', [
                'company_id' => $company->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return BillingPortalSessionResult::failure('provider_error', 'Stripe was unable to create a billing portal session.', $this->fallbackUrl());
        }

        $url = $response->json('url');

        if (blank($url)) {
            return BillingPortalSessionResult::failure('missing_portal_url', 'Stripe did not provide a portal url.', $this->fallbackUrl());
        }

        return BillingPortalSessionResult::success($url);
    }

    private function portalReturnUrl(): string
    {
        $configured = config('services.stripe.portal_return_url');

        if ($configured) {
            return $configured;
        }

        return rtrim(config('app.url', $this->url->to('/')), '/').'/app/settings/billing';
    }

    private function fallbackUrl(): string
    {
        $configured = config('services.stripe.portal_fallback_url');

        if ($configured) {
            return $configured;
        }

        return 'mailto:billing@elements.supply';
    }
}
