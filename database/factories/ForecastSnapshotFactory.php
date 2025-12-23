<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ForecastSnapshot;
use App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;

class ForecastSnapshotFactory extends Factory
{
    protected $model = ForecastSnapshot::class;

    public function definition(): array
    {
        $periodStart = $this->faker->dateTimeBetween('-30 days', 'now');
        $periodEnd = (clone $periodStart)->modify('+6 days');

        return [
            'company_id' => Company::factory(),
            'part_id' => Part::factory(),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'demand_qty' => $this->faker->randomFloat(3, 10, 500),
            'avg_daily_demand' => $this->faker->randomFloat(3, 1, 50),
            'method' => $this->faker->randomElement(['actual', 'sma', 'ema']),
            'alpha' => $this->faker->randomFloat(3, 0.1, 0.9),
            'on_hand_qty' => $this->faker->randomFloat(3, 0, 500),
            'on_order_qty' => $this->faker->randomFloat(3, 0, 500),
            'safety_stock_qty' => $this->faker->randomFloat(3, 0, 100),
            'projected_runout_days' => $this->faker->randomFloat(2, 10, 120),
            'horizon_days' => $this->faker->numberBetween(7, 60),
        ];
    }
}
