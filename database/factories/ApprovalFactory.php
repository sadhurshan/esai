<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Models\Approval;
use App\Models\ApprovalRule;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Approval>
 */
class ApprovalFactory extends Factory
{
    protected $model = Approval::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'approval_rule_id' => ApprovalRule::factory(),
            'target_type' => 'purchase_order',
            'target_id' => $this->faker->randomNumber(),
            'level_no' => 1,
            'status' => ApprovalStatus::Pending,
            'comment' => null,
            'approved_by_id' => null,
            'approved_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Approval $approval): void {
            if ($approval->relationLoaded('approvalRule')) {
                $rule = $approval->getRelation('approvalRule');
            } else {
                $rule = $approval->approvalRule;
            }

            if ($rule instanceof ApprovalRule && $approval->company_id !== (int) $rule->company_id) {
                $rule->company_id = $approval->company_id;
            }
        })->afterCreating(function (Approval $approval): void {
            $rule = $approval->approvalRule;

            if ($rule instanceof ApprovalRule && $approval->company_id !== (int) $rule->company_id) {
                $rule->company_id = $approval->company_id;
                $rule->save();
            }
        });
    }
}
