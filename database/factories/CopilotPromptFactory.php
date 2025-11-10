<?php

namespace Database\Factories;

use App\Models\CopilotPrompt;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CopilotPrompt>
 */
class CopilotPromptFactory extends Factory
{
    protected $model = CopilotPrompt::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'status' => 'completed',
            'metrics' => ['cycle_time'],
            'query' => 'Show last month cycle time',
            'response' => [
                'items' => [
                    ['type' => 'cycle_time', 'value' => $this->faker->randomFloat(2, 1, 10)],
                ],
            ],
            'meta' => ['source' => 'factory'],
            'latency_ms' => $this->faker->numberBetween(50, 800),
        ];
    }
}
