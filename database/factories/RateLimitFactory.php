<?php

namespace Database\Factories;

use App\Enums\RateLimitScope;
use App\Models\RateLimit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RateLimit>
 */
class RateLimitFactory extends Factory
{
    protected $model = RateLimit::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'window_seconds' => 60,
            'max_requests' => 100,
            'scope' => RateLimitScope::Api,
            'active' => true,
        ];
    }
}
