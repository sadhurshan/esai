<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceLineFactory extends Factory
{
    protected $model = InvoiceLine::class;

    public function definition(): array
    {
        $unitPrice = $this->faker->randomFloat(2, 5, 500);
        $quantity = $this->faker->numberBetween(1, 25);

        return [
            'invoice_id' => Invoice::factory(),
            'po_line_id' => null,
            'description' => $this->faker->sentence(3),
            'quantity' => $quantity,
            'uom' => 'EA',
            'unit_price' => $unitPrice,
            'currency' => 'USD',
            'unit_price_minor' => (int) round($unitPrice * 100),
            'line_total_minor' => (int) round($unitPrice * 100) * $quantity,
        ];
    }
}
