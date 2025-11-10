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
        $currency = 'USD';
        $minorUnit = 2;
        $amountMinor = $this->faker->numberBetween(5_000, 150_000);
        $amount = number_format($amountMinor / (10 ** $minorUnit), $minorUnit, '.', '');

        return [
            'company_id' => Company::factory(),
            'invoice_id' => Invoice::factory(),
            'purchase_order_id' => PurchaseOrder::factory(),
            'grn_id' => null,
            'issued_by' => null,
            'approved_by' => null,
            'credit_number' => 'CN-'.$this->faker->unique()->numerify('########'),
            'currency' => $currency,
            'amount' => $amount,
            'amount_minor' => $amountMinor,
            'reason' => $this->faker->sentence(6),
            'status' => CreditNoteStatus::Draft,
            'review_comment' => null,
            'approved_at' => null,
        ];
    }
}
