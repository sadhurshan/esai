<?php

namespace Database\Factories;

use App\Models\RFQ;
use App\Models\RFQQuote;
use App\Models\Supplier;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RFQQuote>
 */
class RFQQuoteFactory extends Factory
{
    protected $model = RFQQuote::class;

    public function configure(): static
    {
        return $this
            ->afterMaking(function (RFQQuote $quote): void {
                $quote->company_id ??= $this->resolveCompanyId($quote);
            })
            ->afterCreating(function (RFQQuote $quote): void {
                if ($quote->company_id === null) {
                    $quote->forceFill(['company_id' => $this->resolveCompanyId($quote)])->save();
                }
            });
    }

    public function definition(): array
    {
        $via = $this->faker->randomElement(['direct', 'bidding']);

        return [
            'company_id' => null,
            'rfq_id' => RFQ::factory(),
            'supplier_id' => Supplier::factory(),
            'unit_price_usd' => $this->faker->randomFloat(2, 45, 3200),
            'lead_time_days' => $this->faker->numberBetween(7, 60),
            'note' => $this->faker->optional(0.4)->sentences($this->faker->numberBetween(1, 2), true),
            'attachment_path' => $this->faker->optional(0.3)->randomElement([
                'attachments/quote-notes.txt',
                'attachments/spec-sheet.pdf',
                'attachments/quote-summary.docx',
            ]),
            'via' => $via,
            'submitted_at' => $this->faker->dateTimeBetween('-60 days', 'now'),
        ];
    }

    private function resolveCompanyId(RFQQuote $quote): ?int
    {
        if ($quote->relationLoaded('rfq') && $quote->rfq instanceof RFQ) {
            return $quote->rfq->company_id;
        }

        if ($quote->rfq_id === null) {
            return null;
        }

        return CompanyContext::bypass(static function () use ($quote): ?int {
            return RFQ::query()->withoutGlobalScopes()->whereKey($quote->rfq_id)->value('company_id');
        });
    }
}
