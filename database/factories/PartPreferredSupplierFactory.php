<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Part;
use App\Models\PartPreferredSupplier;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartPreferredSupplier>
 */
class PartPreferredSupplierFactory extends Factory
{
    protected $model = PartPreferredSupplier::class;

    public function definition(): array
    {
        $companyFactory = Company::factory();

        return [
            'company_id' => $companyFactory,
            'part_id' => Part::factory()->for($companyFactory),
            'supplier_id' => Supplier::factory()->for($companyFactory),
            'supplier_name' => $this->faker->company(),
            'priority' => $this->faker->numberBetween(1, 5),
            'notes' => $this->faker->sentence(),
        ];
    }
}
