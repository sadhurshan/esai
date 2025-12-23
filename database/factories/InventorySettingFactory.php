<?php

namespace Database\Factories;

use App\Enums\InventoryPolicy;
use App\Models\Company;
use App\Models\InventorySetting;
use App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventorySettingFactory extends Factory
{
    protected $model = InventorySetting::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'part_id' => Part::factory(),
            'min_qty' => $this->faker->randomFloat(3, 5, 50),
            'max_qty' => $this->faker->randomFloat(3, 60, 200),
            'safety_stock' => $this->faker->randomFloat(3, 1, 40),
            'reorder_qty' => $this->faker->randomFloat(3, 10, 80),
            'lead_time_days' => $this->faker->numberBetween(5, 30),
            'lot_size' => $this->faker->randomFloat(3, 1, 20),
            'policy' => InventoryPolicy::ForecastDriven,
        ];
    }
}
