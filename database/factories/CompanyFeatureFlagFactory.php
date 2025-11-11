<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyFeatureFlag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CompanyFeatureFlag>
 */
class CompanyFeatureFlagFactory extends Factory
{
    protected $model = CompanyFeatureFlag::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'key' => 'feature_'.Str::lower(Str::random(6)),
            'value' => [
                'enabled' => true,
                'notes' => $this->faker->sentence(),
            ],
        ];
    }
}
