<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
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
        $buyerCompany = Company::factory();
        $supplierCompany = Company::factory();
        $subtotalMinor = $this->faker->numberBetween(10_000, 250_000);
        $taxMinor = (int) round($subtotalMinor * 0.1);
        $totalMinor = $subtotalMinor + $taxMinor;

        return [
            'company_id' => $buyerCompany,
            'purchase_order_id' => PurchaseOrder::factory()->for($buyerCompany),
            'supplier_id' => Supplier::factory()->for($buyerCompany),
            'supplier_company_id' => $supplierCompany,
            'invoice_number' => 'INV-'.$this->faker->unique()->numerify('####'),
            'invoice_date' => $this->faker->dateTimeBetween('-10 days', 'now'),
            'due_date' => $this->faker->dateTimeBetween('+10 days', '+45 days'),
            'currency' => 'USD',
            'subtotal' => $subtotalMinor / 100,
            'tax_amount' => $taxMinor / 100,
            'total' => $totalMinor / 100,
            'subtotal_minor' => $subtotalMinor,
            'tax_minor' => $taxMinor,
            'total_minor' => $totalMinor,
            'status' => InvoiceStatus::Draft->value,
            'matched_status' => 'pending',
            'created_by_type' => 'buyer',
            'created_by_id' => null,
            'submitted_at' => null,
            'reviewed_at' => null,
            'reviewed_by_id' => null,
            'review_note' => null,
            'payment_reference' => null,
            'document_id' => null,
        ];
    }
}
