<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => strtoupper(Str::random(5)),
            'name' => $this->faker->company().' Warehouse',
            'address_json' => [
                'line1' => $this->faker->streetAddress(),
                'city' => $this->faker->city(),
                'country' => $this->faker->countryCode(),
            ],
            'active' => true,
        ];
    }
}
