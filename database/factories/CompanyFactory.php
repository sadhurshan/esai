<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name.'-'.$this->faker->unique()->randomNumber()),
            'status' => 'active',
            'supplier_status' => 'none',
            'directory_visibility' => 'private',
            'supplier_profile_completed_at' => null,
            'region' => $this->faker->randomElement(['us', 'eu', 'asia']),
            'owner_user_id' => null,
            'rfqs_monthly_used' => 0,
            'storage_used_mb' => 0,
            'stripe_id' => null,
            'plan_code' => 'starter',
            'trial_ends_at' => null,
        ];
    }
}
