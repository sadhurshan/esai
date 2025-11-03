<?php

namespace Database\Factories;

use App\Models\RFQ;
use App\Models\RfqItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfqItem>
 */
class RfqItemFactory extends Factory
{
    protected $model = RfqItem::class;

    public function definition(): array
    {
        return [
            'rfq_id' => RFQ::factory(),
            'line_no' => $this->faker->unique()->numberBetween(1, 10),
            'part_name' => $this->faker->words(3, true),
            'spec' => $this->faker->sentence(),
            'quantity' => $this->faker->numberBetween(1, 100),
            'uom' => 'pcs',
            'target_price' => $this->faker->randomFloat(2, 10, 500),
        ];
    }
}
