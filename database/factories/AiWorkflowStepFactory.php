<?php

namespace Database\Factories;

use App\Models\AiWorkflowStep;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiWorkflowStep>
 */
class AiWorkflowStepFactory extends Factory
{
    protected $model = AiWorkflowStep::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'workflow_id' => (string) Str::uuid(),
            'step_index' => 0,
            'action_type' => 'rfq_draft',
            'input_json' => [],
            'approval_status' => AiWorkflowStep::APPROVAL_PENDING,
        ];
    }
}
