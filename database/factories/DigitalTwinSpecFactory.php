<?php

namespace Database\Factories;

use App\Models\DigitalTwin;
use App\Models\DigitalTwinSpec;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DigitalTwinSpec>
 */
class DigitalTwinSpecFactory extends Factory
{
    protected $model = DigitalTwinSpec::class;

    public function definition(): array
    {
        return [
            'digital_twin_id' => DigitalTwin::factory(),
            'name' => $this->faker->words(2, true),
            'value' => (string) $this->faker->numberBetween(10, 2000),
            'uom' => $this->faker->randomElement(['mm', 'cm', 'kg', 'N']),
            'sort_order' => $this->faker->numberBetween(0, 10),
        ];
    }
}
