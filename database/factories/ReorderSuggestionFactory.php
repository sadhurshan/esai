<?php

namespace Database\Factories;

use App\Enums\ReorderMethod;
use App\Enums\ReorderStatus;
use App\Models\Company;
use App\Models\Part;
use App\Models\ReorderSuggestion;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReorderSuggestion>
 */
class ReorderSuggestionFactory extends Factory
{
    protected $model = ReorderSuggestion::class;

    public function definition(): array
    {
        $horizonStart = $this->faker->dateTimeBetween('-7 days', 'now');
        $horizonEnd = (clone $horizonStart)->modify('+30 days');

        return [
            'company_id' => Company::factory(),
            'part_id' => Part::factory(),
            'warehouse_id' => Warehouse::factory(),
            'suggested_qty' => $this->faker->randomFloat(3, 1, 250),
            'reason' => $this->faker->randomElement([
                'Safety stock breach',
                'Forecasted demand spike',
                'Buffer replenishment',
            ]),
            'horizon_start' => $horizonStart,
            'horizon_end' => $horizonEnd,
            'method' => $this->faker->randomElement(ReorderMethod::values()),
            'generated_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'accepted_at' => null,
            'status' => ReorderStatus::Open,
            'pr_id' => null,
        ];
    }
}
