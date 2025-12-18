<?php

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\Plan;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeCheckoutService
{
    public function __construct(private readonly UrlGenerator $url)
    {
    }

    public function requiresCheckout(Plan $plan): bool
    {
        return ! $this->isComplimentary($plan);
    }

    /**
     * @return array<string, mixed>
     */
    public function createPlanCheckout(Company $company, Plan $plan): array
    {
        $priceId = config("services.stripe.prices.{$plan->code}");

        if ($priceId === null) {
            return $this->fallbackResponse($plan, 'price_unconfigured');
        }

        $secret = config('services.stripe.secret');

        if (blank($secret)) {
            return $this->fallbackResponse($plan, 'secret_missing');
        }

        $successUrl = config('services.stripe.checkout_success_url')
            ?: $this->defaultSuccessUrl();
        $cancelUrl = config('services.stripe.checkout_cancel_url')
            ?: $this->defaultCancelUrl();

        $metadata = [
            'company_id' => (string) $company->id,
            'plan_code' => $plan->code,
        ];

        $payload = [
            'mode' => 'subscription',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'customer_email' => $company->owner?->email,
            'client_reference_id' => 'company_'.$company->id,
            'metadata' => $metadata,
            'subscription_data' => [
                'metadata' => $metadata,
            ],
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
        ];

        if ($company->stripe_id) {
            $payload['customer'] = $company->stripe_id;
        }

        $response = Http::withBasicAuth($secret, '')
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', $payload);

        if ($response->failed()) {
            Log::warning('Failed to create Stripe checkout session', [
                'company_id' => $company->id,
                'plan_code' => $plan->code,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->fallbackResponse($plan, 'provider_error', $response);
        }

        $payload = $response->json();

        return [
            'status' => 'requires_checkout',
            'provider' => 'stripe',
            'session_id' => $payload['id'] ?? null,
            'checkout_url' => $payload['url'] ?? null,
            'mode' => $payload['mode'] ?? 'subscription',
            'requires_payment' => true,
        ];
    }

    private function fallbackResponse(Plan $plan, string $reason, ?Response $response = null): array
    {
        $fallbackUrl = config('services.stripe.fallback_checkout_url')
            ?: $this->url->to('/app/setup/plan').'?mode=change';

        return [
            'status' => $reason,
            'provider' => 'stripe',
            'checkout_url' => $fallbackUrl,
            'requires_payment' => true,
            'response' => $response?->json(),
            'plan_code' => $plan->code,
        ];
    }

    private function defaultSuccessUrl(): string
    {
        return rtrim(config('app.url', $this->url->to('/')), '/')
            .'/billing/success?session_id={CHECKOUT_SESSION_ID}';
    }

    private function defaultCancelUrl(): string
    {
        return rtrim(config('app.url', $this->url->to('/')), '/')
            .'/billing/cancel';
    }

    private function isComplimentary(Plan $plan): bool
    {
        if ($plan->code === 'community') {
            return true;
        }

        $price = $plan->price_usd;

        return $price === null || (float) $price <= 0;
    }
}
