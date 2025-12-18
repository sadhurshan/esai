<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'rfq_id' => RFQ::factory(),
            'supplier_id' => Supplier::factory(),
            'submitted_by' => User::factory(),
            'currency' => 'USD',
            'unit_price' => $this->faker->randomFloat(2, 10, 5000),
            'min_order_qty' => $this->faker->numberBetween(1, 100),
            'lead_time_days' => $this->faker->numberBetween(5, 45),
            'incoterm' => 'FOB',
            'payment_terms' => 'NET 30',
            'notes' => $this->faker->sentence(),
            'status' => 'submitted',
            'revision_no' => 1,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total_price' => 100,
            'subtotal_minor' => 10000,
            'tax_amount_minor' => 0,
            'total_price_minor' => 10000,
            'submitted_at' => now(),
        ];
    }
}
