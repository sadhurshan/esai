<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\TaxCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxCode>
 */
class TaxCodeFactory extends Factory
{
    protected $model = TaxCode::class;

    public function definition(): array
    {
        $code = strtoupper($this->faker->bothify('TX-###'));

        return [
            'company_id' => Company::factory(),
            'code' => $code,
            'name' => $this->faker->sentence(3),
            'type' => $this->faker->randomElement(['vat', 'gst', 'sales', 'withholding', 'custom']),
            'rate_percent' => $this->faker->randomFloat(3, 0, 25),
            'is_compound' => $this->faker->boolean(20),
            'active' => true,
            'meta' => [],
        ];
    }
}
