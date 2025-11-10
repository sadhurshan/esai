<?php

namespace Database\Factories;

use App\Enums\EsgCategory;
use App\Models\SupplierEsgRecord;
use App\Models\Supplier;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SupplierEsgRecord>
 */
class SupplierEsgRecordFactory extends Factory
{
    protected $model = SupplierEsgRecord::class;

    public function definition(): array
    {
        $category = $this->faker->randomElement(EsgCategory::values());

        $data = $category === EsgCategory::Emission->value
            ? [
                'co2e' => $this->faker->randomFloat(2, 10, 100),
                'source' => 'Supplier provided',
            ]
            : null;

        $companyFactory = Company::factory();

        return [
            'company_id' => $companyFactory,
            'supplier_id' => Supplier::factory()->for($companyFactory),
            'category' => $category,
            'name' => $this->faker->sentence(4),
            'description' => $this->faker->sentence(),
            'data_json' => $data,
            'approved_at' => Carbon::now()->subDays($this->faker->numberBetween(0, 30)),
            'expires_at' => Carbon::now()->addDays($this->faker->numberBetween(30, 365)),
        ];
    }
}
