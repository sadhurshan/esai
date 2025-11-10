<?php

namespace Database\Factories;

use App\Models\MaintenanceProcedure;
use App\Models\ProcedureStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcedureStep>
 */
class ProcedureStepFactory extends Factory
{
    protected $model = ProcedureStep::class;

    public function definition(): array
    {
        return [
            'maintenance_procedure_id' => MaintenanceProcedure::factory(),
            'step_no' => $this->faker->numberBetween(1, 10),
            'title' => $this->faker->sentence(4),
            'instruction_md' => $this->faker->paragraphs(2, true),
            'estimated_minutes' => $this->faker->optional()->numberBetween(1, 60),
            'attachments_json' => [],
        ];
    }
}
