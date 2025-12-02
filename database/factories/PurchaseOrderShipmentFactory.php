<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderShipment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PurchaseOrderShipmentFactory extends Factory
{
    protected $model = PurchaseOrderShipment::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'supplier_company_id' => Company::factory(),
            'purchase_order_id' => PurchaseOrder::factory(),
            'shipment_number' => 'SHP-' . Str::upper(Str::random(6)),
            'status' => 'pending',
            'carrier' => 'UPS',
            'tracking_number' => '1Z' . Str::upper(Str::random(10)),
            'shipped_at' => now(),
        ];
    }
}
