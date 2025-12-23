<?php

namespace Database\Factories;

use App\Enums\InventoryTxnType;
use App\Models\Company;
use App\Models\InventoryTxn;
use App\Models\Part;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryTxnFactory extends Factory
{
    protected $model = InventoryTxn::class;

    public function definition(): array
    {
        return [
            'movement_id' => null,
            'company_id' => Company::factory(),
            'part_id' => Part::factory(),
            'warehouse_id' => Warehouse::factory(),
            'bin_id' => null,
            'type' => $this->faker->randomElement([
                InventoryTxnType::Issue,
                InventoryTxnType::AdjustOut,
                InventoryTxnType::TransferOut,
            ]),
            'qty' => $this->faker->randomFloat(3, 1, 50),
            'uom' => 'pcs',
            'ref_type' => null,
            'ref_id' => null,
            'note' => $this->faker->sentence(),
            'performed_by' => null,
        ];
    }
}
