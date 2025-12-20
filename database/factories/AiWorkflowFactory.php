<?php

namespace Database\Factories;

use App\Models\AiWorkflow;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiWorkflow>
 */
class AiWorkflowFactory extends Factory
{
    protected $model = AiWorkflow::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'workflow_id' => (string) Str::uuid(),
            'workflow_type' => 'procurement',
            'status' => AiWorkflow::STATUS_PENDING,
            'current_step' => 0,
            'steps_json' => ['steps' => []],
        ];
    }
}
