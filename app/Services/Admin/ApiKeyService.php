<?php

namespace App\Services\Admin;

use App\Models\ApiKey;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ApiKeyService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{api_key:ApiKey,plain_text_token:string}
     */
    public function create(array $attributes): array
    {
        [$prefix, $token] = $this->generateTokenPair();

        $payload = [
            'company_id' => $attributes['company_id'] ?? null,
            'owner_user_id' => $attributes['owner_user_id'] ?? null,
            'name' => $attributes['name'],
            'token_prefix' => $prefix,
            'token_hash' => $this->hashToken("{$prefix}.{$token}"),
            'scopes' => Arr::wrap($attributes['scopes'] ?? []),
            'active' => $attributes['active'] ?? true,
            'expires_at' => $attributes['expires_at'] ?? null,
        ];

        $apiKey = ApiKey::create($payload);

        $this->auditLogger->created($apiKey);

        return [
            'api_key' => $apiKey->fresh(),
            'plain_text_token' => "{$prefix}.{$token}",
        ];
    }

    /**
     * @return array{api_key:ApiKey,plain_text_token:string}
     */
    public function rotate(ApiKey $apiKey): array
    {
        [$prefix, $token] = $this->generateTokenPair();

        $before = Arr::only($apiKey->getOriginal(), ['token_prefix', 'active', 'expires_at']);

        $apiKey->forceFill([
            'token_prefix' => $prefix,
            'token_hash' => $this->hashToken("{$prefix}.{$token}"),
            'last_used_at' => null,
        ])->save();

        $apiKey->refresh();

        $this->auditLogger->updated($apiKey, $before, Arr::only($apiKey->attributesToArray(), array_keys($before)));

        return [
            'api_key' => $apiKey,
            'plain_text_token' => "{$prefix}.{$token}",
        ];
    }

    public function toggle(ApiKey $apiKey, bool $active): ApiKey
    {
        $before = Arr::only($apiKey->getOriginal(), ['active']);

        $apiKey->forceFill(['active' => $active])->save();

        $apiKey->refresh();

        $this->auditLogger->updated($apiKey, $before, ['active' => $apiKey->active]);

        return $apiKey;
    }

    public function delete(ApiKey $apiKey): void
    {
        $before = Arr::only($apiKey->attributesToArray(), ['name', 'token_prefix']);

        $apiKey->delete();

        $this->auditLogger->deleted($apiKey, $before);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function generateTokenPair(): array
    {
        do {
            $prefix = Str::upper(Str::random(10));
        } while (ApiKey::withTrashed()->where('token_prefix', $prefix)->exists());

        $token = Str::random(48);

        return [$prefix, $token];
    }

    private function hashToken(string $token): string
    {
        return hash_hmac('sha256', $token, config('app.key'));
    }
}
