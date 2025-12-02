<?php

namespace Database\Factories;

use App\Enums\RfpStatus;
use App\Models\Company;
use App\Models\Rfp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rfp>
 */
class RfpFactory extends Factory
{
    protected $model = Rfp::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'created_by' => User::factory(),
            'updated_by' => null,
            'title' => fake()->sentence(6),
            'status' => RfpStatus::Draft->value,
            'problem_objectives' => fake()->paragraphs(2, true),
            'scope' => fake()->paragraph(),
            'timeline' => sprintf('Q%s %s', fake()->numberBetween(1, 4), fake()->numberBetween(2025, 2027)),
            'evaluation_criteria' => implode(', ', fake()->words(4)),
            'proposal_format' => 'PDF with cost breakdown',
            'ai_assist_enabled' => false,
            'ai_suggestions' => null,
            'meta' => [
                'budget_estimate' => fake()->numberBetween(10000, 50000),
            ],
        ];
    }

    public function published(): self
    {
        return $this->state(fn () => [
            'status' => RfpStatus::Published->value,
            'published_at' => now(),
        ]);
    }
}
