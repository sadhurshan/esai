<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\UsageSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<UsageSnapshot>
 */
class UsageSnapshotFactory extends Factory
{
    protected $model = UsageSnapshot::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'date' => Carbon::now()->toDateString(),
            'rfqs_count' => $this->faker->numberBetween(0, 50),
            'quotes_count' => $this->faker->numberBetween(0, 50),
            'pos_count' => $this->faker->numberBetween(0, 50),
            'storage_used_mb' => $this->faker->numberBetween(0, 2048),
        ];
    }
}
