<?php

namespace Database\Factories;

use App\Models\AiActionDraft;
use App\Models\AiActionFeedback;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiActionFeedback>
 */
class AiActionFeedbackFactory extends Factory
{
    protected $model = AiActionFeedback::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'ai_action_draft_id' => AiActionDraft::factory(),
            'user_id' => User::factory(),
            'rating' => $this->faker->numberBetween(1, 5),
            'comment' => $this->faker->optional()->sentences(1, true),
            'metadata' => [],
        ];
    }
}
