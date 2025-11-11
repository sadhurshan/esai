<?php

namespace App\Services\Admin;

use App\Enums\RateLimitScope;
use App\Models\RateLimit;
use App\Support\Audit\AuditLogger;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class RateLimitService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CacheRepository $cache
    ) {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): RateLimit
    {
        $rateLimit = RateLimit::create([
            'company_id' => $attributes['company_id'] ?? null,
            'window_seconds' => $attributes['window_seconds'],
            'max_requests' => $attributes['max_requests'],
            'scope' => $attributes['scope'],
            'active' => $attributes['active'] ?? true,
        ]);

        $this->auditLogger->created($rateLimit);

        return $rateLimit->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(RateLimit $rateLimit, array $attributes): RateLimit
    {
        $before = Arr::only($rateLimit->getOriginal(), ['company_id', 'window_seconds', 'max_requests', 'scope', 'active']);

        $rateLimit->fill($attributes)->save();

        $rateLimit->refresh();

        $this->auditLogger->updated($rateLimit, $before, Arr::only($rateLimit->attributesToArray(), array_keys($before)));

        return $rateLimit;
    }

    public function delete(RateLimit $rateLimit): void
    {
        $before = Arr::only($rateLimit->attributesToArray(), ['company_id', 'scope']);

        $rateLimit->delete();

        $this->auditLogger->deleted($rateLimit, $before);
    }

    public function hit(?int $companyId, RateLimitScope $scope): bool
    {
        $limit = $this->resolveLimit($companyId, $scope);

        if ($limit === null) {
            return true;
        }

        $window = $limit->window_seconds;
        $bucket = (int) floor(now()->timestamp / $window);
        $cacheKey = $this->cacheKey($limit->company_id, $scope, $bucket);

        if (! $this->cache->has($cacheKey)) {
            $this->cache->put($cacheKey, 0, $window);
        }

        $count = (int) $this->cache->increment($cacheKey);

        return $count <= $limit->max_requests;
    }

    private function resolveLimit(?int $companyId, RateLimitScope $scope): ?RateLimit
    {
        if ($companyId !== null) {
            $limit = RateLimit::query()
                ->where('company_id', $companyId)
                ->where('scope', $scope)
                ->where('active', true)
                ->first();

            if ($limit !== null) {
                return $limit;
            }
        }

        return RateLimit::query()
            ->whereNull('company_id')
            ->where('scope', $scope)
            ->where('active', true)
            ->first();
    }

    private function cacheKey(?int $companyId, RateLimitScope $scope, int $bucket): string
    {
        $tenantKey = $companyId !== null ? (string) $companyId : 'global';

        return Str::lower("rate_limit:{$scope->value}:{$tenantKey}:{$bucket}");
    }
}
