<?php

namespace Database\Factories;

use App\Models\AnalyticsSnapshot;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<AnalyticsSnapshot>
 */
class AnalyticsSnapshotFactory extends Factory
{
    protected $model = AnalyticsSnapshot::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(AnalyticsSnapshot::TYPES);
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        return [
            'company_id' => Company::factory(),
            'type' => $type,
            'period_start' => $start,
            'period_end' => $end,
            'value' => $this->faker->randomFloat(2, 1, 99),
            'meta' => ['source' => 'factory'],
        ];
    }
}
