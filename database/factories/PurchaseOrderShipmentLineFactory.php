<?php

namespace Database\Factories;

use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderShipment;
use App\Models\PurchaseOrderShipmentLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderShipmentLineFactory extends Factory
{
    protected $model = PurchaseOrderShipmentLine::class;

    public function definition(): array
    {
        return [
            'purchase_order_shipment_id' => PurchaseOrderShipment::factory(),
            'purchase_order_line_id' => PurchaseOrderLine::factory(),
            'qty_shipped' => $this->faker->randomFloat(2, 1, 10),
        ];
    }
}
