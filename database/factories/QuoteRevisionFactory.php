<?php

namespace Database\Factories;

use App\Models\Quote;
use App\Models\QuoteRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteRevision>
 */
class QuoteRevisionFactory extends Factory
{
    protected $model = QuoteRevision::class;

    public function definition(): array
    {
        return [
            'company_id' => Quote::factory()->create()->company_id,
            'quote_id' => Quote::factory(),
            'revision_no' => $this->faker->numberBetween(2, 10),
            'data_json' => [
                'unit_price' => $this->faker->randomFloat(2, 100, 500),
                'lead_time_days' => $this->faker->numberBetween(5, 30),
                'note' => $this->faker->sentence(),
            ],
        ];
    }
}
