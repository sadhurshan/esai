<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetProcedureLink;
use App\Models\MaintenanceProcedure;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<AssetProcedureLink>
 */
class AssetProcedureLinkFactory extends Factory
{
    protected $model = AssetProcedureLink::class;

    public function definition(): array
    {
        $frequencyUnits = ['day', 'week', 'month', 'year'];

        $frequency = $this->faker->numberBetween(1, 12);
        $unit = $this->faker->randomElement($frequencyUnits);
        $lastDone = Carbon::now()->subDays($this->faker->numberBetween(1, 30));

        return [
            'asset_id' => Asset::factory(),
            'maintenance_procedure_id' => MaintenanceProcedure::factory(),
            'frequency_value' => $frequency,
            'frequency_unit' => $unit,
            'last_done_at' => $lastDone,
            'next_due_at' => $lastDone->copy()->addDays($frequency),
            'meta' => [],
        ];
    }
}
