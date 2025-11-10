<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureDigitalTwinAccess
{
    public function handle(Request $request, Closure $next): JsonResponse|Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->errorResponse('Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        /** @var Company|null $company */
        $company = $user->company;

        if (! $company instanceof Company) {
            return $this->errorResponse('Company context required.', Response::HTTP_FORBIDDEN);
        }

        $company->loadMissing('plan');
        $plan = $company->plan;

        if ($plan === null || ! $plan->digital_twin_enabled) {
            return $this->upgradeRequired('digital_twin_disabled');
        }

        if ($this->requiresMaintenance($request) && (! $plan->maintenance_enabled)) {
            return $this->upgradeRequired('maintenance_disabled');
        }

        return $next($request);
    }

    private function requiresMaintenance(Request $request): bool
    {
        $path = $request->path();

        if (Str::contains($path, 'digital-twin/procedures')) {
            return true;
        }

        if (Str::contains($path, 'digital-twin/assets') && Str::contains($path, 'procedures')) {
            return true;
        }

        return Str::contains($path, 'procedures') && Str::contains($path, 'complete');
    }

    private function upgradeRequired(string $code): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Upgrade required',
            'data' => null,
            'errors' => [
                'code' => $code,
                'upgrade_url' => url('/pricing'),
            ],
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
