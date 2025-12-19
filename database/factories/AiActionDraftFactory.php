<?php

namespace Database\Factories;

use App\Models\AiActionDraft;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiActionDraft>
 */
class AiActionDraftFactory extends Factory
{
    protected $model = AiActionDraft::class;

    public function definition(): array
    {
        $actionType = $this->faker->randomElement(AiActionDraft::ACTION_TYPES);

        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'action_type' => $actionType,
            'input_json' => [
                'query' => $this->faker->sentence(),
                'inputs' => [],
                'user_context' => [],
                'filters' => null,
            ],
            'output_json' => [
                'action_type' => $actionType,
                'summary' => $this->faker->sentence(),
                'payload' => ['notes' => $this->faker->sentence()],
                'citations' => [],
                'confidence' => 0.8,
                'needs_human_review' => false,
                'warnings' => [],
            ],
            'citations_json' => [],
            'status' => AiActionDraft::STATUS_DRAFTED,
            'entity_type' => null,
            'entity_id' => null,
        ];
    }

    public function approved(): self
    {
        return $this->state(function (array $attributes): array {
            return [
                'status' => AiActionDraft::STATUS_APPROVED,
                'approved_by' => $attributes['user_id'] ?? User::factory(),
                'approved_at' => now(),
            ];
        });
    }

    public function rejected(): self
    {
        return $this->state(function (): array {
            return [
                'status' => AiActionDraft::STATUS_REJECTED,
                'approved_by' => null,
                'approved_at' => null,
                'rejected_reason' => 'Not a fit.',
            ];
        });
    }
}
