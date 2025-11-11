<?php

namespace Database\Factories;

use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    public function definition(): array
    {
        $prefix = Str::upper(Str::random(10));
        $secret = Str::random(48);

        return [
            'name' => $this->faker->words(2, true),
            'token_prefix' => $prefix,
            'token_hash' => hash_hmac('sha256', "{$prefix}.{$secret}", config('app.key')),
            'scopes' => ['rfq.read'],
            'active' => true,
        ];
    }
}
