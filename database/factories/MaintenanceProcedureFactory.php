<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\MaintenanceProcedure;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MaintenanceProcedure>
 */
class MaintenanceProcedureFactory extends Factory
{
    protected $model = MaintenanceProcedure::class;

    public function definition(): array
    {
        $categories = ['preventive', 'corrective', 'inspection', 'calibration', 'safety'];

        return [
            'company_id' => Company::factory(),
            'code' => 'MP-'.strtoupper(Str::random(5)),
            'title' => $this->faker->sentence(3),
            'category' => $this->faker->randomElement($categories),
            'estimated_minutes' => $this->faker->numberBetween(15, 240),
            'instructions_md' => $this->faker->paragraph(),
            'tools_json' => [],
            'safety_json' => [],
            'meta' => [],
        ];
    }
}
