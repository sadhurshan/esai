<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderLineFactory extends Factory
{
    protected $model = PurchaseOrderLine::class;

    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'line_no' => $this->faker->unique()->numberBetween(1, 20),
            'description' => $this->faker->sentence(3),
            'quantity' => $this->faker->numberBetween(1, 50),
            'uom' => 'EA',
            'unit_price' => $this->faker->randomFloat(2, 10, 1000),
            'received_qty' => 0,
            'receiving_status' => 'open',
        ];
    }
}
