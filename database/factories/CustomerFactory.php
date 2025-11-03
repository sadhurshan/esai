<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->company(),
            'email' => $this->faker->companyEmail(),
            'stripe_id' => 'cus_'.$this->faker->regexify('[A-Za-z0-9]{14}'),
            'pm_type' => 'card',
            'pm_last_four' => (string) $this->faker->numberBetween(1000, 9999),
            'default_payment_method' => null,
        ];
    }
}
