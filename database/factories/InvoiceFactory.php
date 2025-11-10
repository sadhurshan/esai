<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'purchase_order_id' => PurchaseOrder::factory(),
            'supplier_id' => Supplier::factory(),
            'invoice_number' => 'INV-'.$this->faker->unique()->numerify('####'),
            'currency' => 'USD',
            'subtotal' => $this->faker->randomFloat(2, 100, 5000),
            'tax_amount' => $this->faker->randomFloat(2, 0, 500),
            'total' => $this->faker->randomFloat(2, 100, 5500),
            'status' => 'pending',
            'document_id' => null,
        ];
    }
}
