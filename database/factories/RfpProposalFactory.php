<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Rfp;
use App\Models\RfpProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfpProposal>
 */
class RfpProposalFactory extends Factory
{
    protected $model = RfpProposal::class;

    public function definition(): array
    {
        return [
            'rfp_id' => Rfp::factory(),
            'company_id' => Company::factory(),
            'supplier_company_id' => Company::factory(),
            'submitted_by' => User::factory(),
            'status' => 'submitted',
            'price_total' => fake()->randomFloat(2, 1000, 50000),
            'price_total_minor' => fake()->numberBetween(100000, 5000000),
            'currency' => 'USD',
            'lead_time_days' => fake()->numberBetween(10, 90),
            'approach_summary' => fake()->paragraph(),
            'schedule_summary' => fake()->sentence(10),
            'value_add_summary' => fake()->sentence(12),
            'attachments_count' => 0,
            'meta' => [
                'milestones' => fake()->words(3),
            ],
        ];
    }
}
