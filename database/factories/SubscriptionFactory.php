<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_id' => Customer::factory(),
            'name' => 'primary',
            'stripe_id' => 'sub_'.$this->faker->regexify('[A-Za-z0-9]{14}'),
            'stripe_status' => 'active',
            'stripe_plan' => 'price_'.$this->faker->regexify('[A-Za-z0-9]{8}'),
            'quantity' => 1,
            'trial_ends_at' => null,
            'ends_at' => null,
        ];
    }
}
