<?php

namespace App\Http\Middleware;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Support\ActivePersonaContext;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscribed
{
    public function handle(Request $request, Closure $next): JsonResponse|Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->errorResponse('Authentication required.', 401);
        }

        /** @var Company|null $company */
        $company = $user->company;

        if ($company === null) {
            return $this->errorResponse('Subscription unavailable for this user.', 403);
        }

        if (ActivePersonaContext::isSupplier()) {
            return $next($request);
        }

        $company->loadMissing(['plan', 'subscriptions']);

        $status = $company->status;

        if (
            ($status instanceof CompanyStatus && ($status === CompanyStatus::Pending || $status === CompanyStatus::PendingVerification))
            || in_array($status, [CompanyStatus::Pending->value, CompanyStatus::PendingVerification->value], true)
        ) {
            return $next($request);
        }

        if ($this->isInGraceReadOnlyWindow($company)) {
            if ($this->isReadOnlyRequest($request)) {
                $request->attributes->set('billing.read_only', true);
                $request->attributes->set('billing.grace_expires_at', $company->billingGraceEndsAt());

                return $next($request);
            }

            return $this->upgradeRequired('subscription_past_due', array_filter([
                'message' => 'Your payment method needs attention before you can resume write access.',
                'grace_expires_at' => $company->billingGraceEndsAt()?->toIso8601String(),
                'read_only' => true,
            ]));
        }

        if (! $this->hasActiveSubscription($company)) {
            if (app()->environment('testing')) {
                logger()->debug('ensure_subscribed_inactive', [
                    'company_id' => $company->getKey(),
                    'plan_code' => $company->plan?->code,
                    'plan_price' => $company->plan?->price_usd,
                    'subscription_count' => $company->subscriptions()->count(),
                    'subscription_statuses' => $company->subscriptions()->pluck('stripe_status')->all(),
                    'subscription_ids' => $company->subscriptions()->pluck('id')->all(),
                    'billing_status' => $company->billingStatus(),
                    'auth_user_company_id' => optional($user)->company_id ?? null,
                ]);
            }
            return $this->upgradeRequired('subscription_inactive', [
                'message' => 'Your subscription is inactive or has expired.',
            ]);
        }

        if ($violation = $this->limitExceeded($company)) {
            return $this->upgradeRequired($violation['code'], $violation['context']);
        }

        return $next($request);
    }

    private function hasActiveSubscription(Company $company): bool
    {
        $plan = $company->plan;

        if ($plan !== null && ($plan->code === 'community' || $plan->price_usd === null || (float) $plan->price_usd <= 0.0)) {
            return true;
        }

        if ($company->billingStatus() === 'trialing') {
            return true;
        }

        $subscription = $company->currentSubscription();

        return $subscription?->isActive() === true;
    }

    private function isInGraceReadOnlyWindow(Company $company): bool
    {
        return $company->isInBillingGracePeriod();
    }

    private function isReadOnlyRequest(Request $request): bool
    {
        return in_array(strtoupper($request->getMethod()), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    /**
     * @return array{code: string, context: array<string, mixed>}|null
     */
    private function limitExceeded(Company $company): ?array
    {
        $plan = $company->plan;

        if ($plan === null) {
            return [
                'code' => 'plan_missing',
                'context' => [
                    'message' => 'No subscription plan found for this company.',
                ],
            ];
        }

        if ($plan->rfqs_per_month > 0 && $company->rfqs_monthly_used >= $plan->rfqs_per_month) {
            return [
                'code' => 'rfqs_per_month',
                'context' => [
                    'limit' => $plan->rfqs_per_month,
                    'usage' => $company->rfqs_monthly_used,
                ],
            ];
        }

        if ($plan->invoices_per_month > 0 && $company->invoices_monthly_used >= $plan->invoices_per_month) {
            return [
                'code' => 'invoices_per_month',
                'context' => [
                    'limit' => $plan->invoices_per_month,
                    'usage' => $company->invoices_monthly_used,
                ],
            ];
        }

        if ($plan->users_max > 0) {
            $activeUsers = $company->users()->count();

            if ($activeUsers >= $plan->users_max) {
                return [
                    'code' => 'users_max',
                    'context' => [
                        'limit' => $plan->users_max,
                        'usage' => $activeUsers,
                    ],
                ];
            }
        }

        if ($plan->storage_gb > 0) {
            $limitMb = $plan->storage_gb * 1024;

            if ($company->storage_used_mb >= $limitMb) {
                return [
                    'code' => 'storage_gb',
                    'context' => [
                        'limit' => $plan->storage_gb,
                        'usage_mb' => $company->storage_used_mb,
                    ],
                ];
            }
        }

        return null;
    }

    private function upgradeRequired(string $code, array $context = []): JsonResponse
    {
        return ApiResponse::error('Upgrade required', 402, array_merge($context, [
            'code' => $code,
            'upgrade_url' => $this->upgradeUrl(),
        ]));
    }

    private function upgradeUrl(): string
    {
        return url('/app/setup/plan').'?mode=change';
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return ApiResponse::error($message, $status);
    }
}
