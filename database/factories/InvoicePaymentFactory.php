<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoicePayment>
 */
class InvoicePaymentFactory extends Factory
{
    protected $model = InvoicePayment::class;

    public function definition(): array
    {
        $amountMinor = $this->faker->numberBetween(5_000, 250_000);

        return [
            'company_id' => Company::factory(),
            'invoice_id' => Invoice::factory(),
            'created_by_id' => User::factory(),
            'amount' => $amountMinor / 100,
            'amount_minor' => $amountMinor,
            'currency' => 'USD',
            'paid_at' => $this->faker->dateTimeBetween('-2 weeks', 'now'),
            'payment_reference' => $this->faker->unique()->bothify('PAY-####'),
            'payment_method' => $this->faker->randomElement(['ach', 'wire', 'card']),
            'note' => $this->faker->sentence(),
        ];
    }
}
