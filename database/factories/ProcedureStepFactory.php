<?php

namespace Database\Factories;

use App\Models\Company;
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
            'company_id' => null,
            'maintenance_procedure_id' => null,
            'step_no' => $this->faker->numberBetween(1, 10),
            'title' => $this->faker->sentence(4),
            'instruction_md' => $this->faker->paragraphs(2, true),
            'estimated_minutes' => $this->faker->optional()->numberBetween(1, 60),
            'attachments_json' => [],
        ];
    }

    public function configure(): self
    {
        return $this->afterMaking(function (ProcedureStep $step): void {
            if ($step->maintenance_procedure_id === null) {
                $companyId = $step->company_id ?? Company::factory()->create()->id;
                $procedure = MaintenanceProcedure::factory()->create([
                    'company_id' => $companyId,
                ]);
                $step->maintenance_procedure_id = $procedure->id;
                $step->company_id = $procedure->company_id;
            } else {
                $procedureCompany = MaintenanceProcedure::query()
                    ->whereKey($step->maintenance_procedure_id)
                    ->value('company_id');

                if ($procedureCompany !== null) {
                    $step->company_id = $procedureCompany;
                } elseif ($step->company_id === null) {
                    $step->company_id = Company::factory()->create()->id;
                }
            }

            if ($step->company_id === null) {
                $step->company_id = Company::factory()->create()->id;
            }
        });
    }
}
