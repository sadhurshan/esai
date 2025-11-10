<?php

namespace Database\Factories;

use App\Enums\CreditNoteStatus;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CreditNote>
 */
class CreditNoteFactory extends Factory
{
    protected $model = CreditNote::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'invoice_id' => Invoice::factory(),
            'purchase_order_id' => PurchaseOrder::factory(),
            'grn_id' => null,
            'issued_by' => null,
            'approved_by' => null,
            'credit_number' => 'CN-'.$this->faker->unique()->numerify('########'),
            'currency' => 'USD',
            'amount' => $this->faker->randomFloat(2, 50, 1000),
            'reason' => $this->faker->sentence(6),
            'status' => CreditNoteStatus::Draft,
            'review_comment' => null,
            'approved_at' => null,
        ];
    }
}
