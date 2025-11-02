<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        $capabilities = [
            'CNC Milling',
            'CNC Turning',
            'Sheet Metal',
            'Injection Molding',
            '3D Printing',
        ];

        $materials = [
            'Aluminum',
            'Stainless Steel',
            'Mild Steel',
            'ABS',
            'Nylon',
            'Brass',
            'Copper',
        ];

        $regions = [
            'US-West',
            'US-East',
            'Canada',
            'Mexico',
            'Germany',
            'Poland',
            'United Kingdom',
            'Japan',
            'Singapore',
            'India',
        ];

        return [
            'name' => $this->faker->unique()->company(),
            'rating' => $this->faker->numberBetween(3, 5),
            'capabilities' => $this->faker->randomElements($capabilities, $this->faker->numberBetween(2, 4)),
            'materials' => $this->faker->randomElements($materials, $this->faker->numberBetween(3, 5)),
            'location_region' => $this->faker->randomElement($regions),
            'min_order_qty' => $this->faker->numberBetween(1, 500),
            'avg_response_hours' => $this->faker->numberBetween(8, 72),
        ];
    }
}
