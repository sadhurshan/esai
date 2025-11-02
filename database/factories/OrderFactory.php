<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $statuses = ['pending', 'confirmed', 'in_production', 'delivered', 'cancelled'];
        $partyType = $this->faker->randomElement(['supplier', 'customer']);

        return [
            'number' => 'PO-'.sprintf('%05d', $this->faker->unique()->numberBetween(0, 99999)),
            'party_type' => $partyType,
            'party_name' => $partyType === 'supplier'
                ? $this->faker->company()
                : $this->faker->company().' Procurement',
            'item_name' => ucfirst($this->faker->words($this->faker->numberBetween(2, 4), true)),
            'quantity' => $this->faker->numberBetween(5, 400),
            'total_usd' => $this->faker->randomFloat(2, 1500, 250000),
            'ordered_at' => $this->faker->dateTimeBetween('-120 days', '+60 days'),
            'status' => $this->faker->randomElement($statuses),
        ];
    }
}
