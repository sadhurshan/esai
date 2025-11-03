<?php

namespace App\Http\Middleware;

use App\Models\Company;
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

        $company->loadMissing(['plan', 'subscriptions']);

        if (! $this->hasActiveSubscription($company)) {
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
        if ($company->billingStatus() === 'trialing') {
            return true;
        }

        $subscription = $company->currentSubscription();

        return $subscription?->isActive() === true;
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
        return response()->json([
            'status' => 'error',
            'message' => 'Plan limit exceeded',
            'data' => null,
            'errors' => array_merge($context, [
                'code' => $code,
                'upgrade_url' => url('/pricing'),
            ]),
        ], 402);
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'data' => null,
        ], $status);
    }
}
