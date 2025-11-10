<?php

namespace Database\Factories;

use App\Models\ApprovalRule;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalRule>
 */
class ApprovalRuleFactory extends Factory
{
    protected $model = ApprovalRule::class;

    public function definition(): array
    {
        $target = $this->faker->randomElement(['rfq', 'purchase_order', 'change_order', 'invoice', 'ncr']);

        return [
            'company_id' => Company::factory(),
            'target_type' => $target,
            'threshold_min' => 0,
            'threshold_max' => null,
            'levels_json' => [
                [
                    'level_no' => 1,
                    'approver_role' => 'buyer_admin',
                    'approver_user_id' => null,
                ],
            ],
            'active' => true,
        ];
    }
}
