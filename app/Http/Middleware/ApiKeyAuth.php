<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuth
{
    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        $token = $this->extractBearerToken($request);

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

        $scopes = Arr::wrap($apiKey->scopes ?? []);

        if ($requiredScope !== null && ! in_array($requiredScope, $scopes, true)) {
            return $this->deny(Response::HTTP_FORBIDDEN, 'API key scope missing.');
        }

        $request->attributes->set('auth.api_key', $apiKey);
        $request->attributes->set('auth.api_scopes', $scopes);

        if ($apiKey->company_id !== null) {
            $request->attributes->set('auth.company_id', $apiKey->company_id);
        }

        return $next($request);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $authorization = $request->headers->get('Authorization');

        if (! is_string($authorization)) {
            return null;
        }

        if (! preg_match('/^Bearer\s+(.*)$/i', $authorization, $matches)) {
            return null;
        }

        return trim($matches[1]);
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

    private function deny(int $status, string $message): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'data' => null,
        ], $status);
    }
}
