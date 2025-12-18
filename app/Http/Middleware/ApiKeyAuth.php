<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuth
{
    public function handle(Request $request, Closure $next, string ...$requiredScopes): Response
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            return $this->deny(Response::HTTP_UNAUTHORIZED, 'API key missing.');
        }

        [$prefix, $secret] = $this->splitToken($token);

        if ($prefix === null || $secret === null) {
            return $this->deny(Response::HTTP_UNAUTHORIZED, 'Invalid API key format.');
        }

        $apiKey = ApiKey::query()
            ->where('token_prefix', $prefix)
            ->where('active', true)
            ->first();

        if ($apiKey === null) {
            return $this->deny(Response::HTTP_UNAUTHORIZED, 'API key not recognized.');
        }

        if ($apiKey->expires_at !== null && now()->greaterThan($apiKey->expires_at)) {
            return $this->deny(Response::HTTP_UNAUTHORIZED, 'API key expired.');
        }

        if (! hash_equals($apiKey->token_hash, $this->hashToken($token))) {
            return $this->deny(Response::HTTP_UNAUTHORIZED, 'API key invalid.');
        }

        $scopes = array_values(array_filter(Arr::wrap($apiKey->scopes ?? []), static fn ($scope): bool => is_string($scope) && $scope !== ''));

        $normalizedRequiredScopes = $this->normalizeScopes($requiredScopes);

        if ($normalizedRequiredScopes !== [] && ! $this->scopesSatisfied($normalizedRequiredScopes, $scopes)) {
            return $this->deny(Response::HTTP_FORBIDDEN, 'API key scope missing.');
        }

        $request->attributes->set('auth.api_key', $apiKey);
        $request->attributes->set('auth.api_scopes', $scopes);

        if ($apiKey->company_id !== null) {
            $request->attributes->set('auth.company_id', $apiKey->company_id);
        }

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $authorization = $request->headers->get('Authorization');

        if (is_string($authorization) && preg_match('/^Bearer\s+(.*)$/i', $authorization, $matches)) {
            $token = trim($matches[1]);

            if ($token !== '') {
                return $token;
            }
        }

        $headerToken = $request->headers->get('X-API-Key');

        if (is_string($headerToken)) {
            $value = trim($headerToken);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function splitToken(string $token): array
    {
        $parts = explode('.', $token, 2);

        if (count($parts) !== 2) {
            return [null, null];
        }

        return [$parts[0] !== '' ? $parts[0] : null, $parts[1] !== '' ? $parts[1] : null];
    }

    private function hashToken(string $token): string
    {
        return hash_hmac('sha256', $token, config('app.key'));
    }

    /**
     * @param  array<int, string>  $scopes
     * @return array<int, string>
     */
    private function normalizeScopes(array $scopes): array
    {
        $normalized = [];

        foreach ($scopes as $scopeGroup) {
            $chunks = preg_split('/[\|,]/', $scopeGroup) ?: [];

            foreach ($chunks as $chunk) {
                $value = trim($chunk);

                if ($value === '') {
                    continue;
                }

                $normalized[$value] = true;
            }
        }

        return array_keys($normalized);
    }

    /**
     * @param  array<int, string>  $required
     * @param  array<int, string>  $granted
     */
    private function scopesSatisfied(array $required, array $granted): bool
    {
        $grantedLookup = [];

        foreach ($granted as $scope) {
            $grantedLookup[$scope] = true;
        }

        foreach ($required as $scope) {
            if (! isset($grantedLookup[$scope])) {
                return false;
            }
        }

        return true;
    }

    private function deny(int $status, string $message): JsonResponse
    {
        return ApiResponse::error($message, $status);
    }
}
