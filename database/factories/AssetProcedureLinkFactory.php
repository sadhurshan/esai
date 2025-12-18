<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetProcedureLink;
use App\Models\Company;
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
            'company_id' => null,
            'asset_id' => null,
            'maintenance_procedure_id' => null,
            'frequency_value' => $frequency,
            'frequency_unit' => $unit,
            'last_done_at' => $lastDone,
            'next_due_at' => $lastDone->copy()->addDays($frequency),
            'meta' => [],
        ];
    }

    public function configure(): self
    {
        return $this->afterMaking(function (AssetProcedureLink $link): void {
            if ($link->company_id === null) {
                $link->company_id = Company::factory()->create()->id;
            }

            if ($link->asset_id === null) {
                $link->asset_id = Asset::factory()->create([
                    'company_id' => $link->company_id,
                ])->id;
            } else {
                $assetCompany = Asset::query()->whereKey($link->asset_id)->value('company_id');
                if ($assetCompany !== null) {
                    $link->company_id = $assetCompany;
                }
            }

            if ($link->maintenance_procedure_id === null) {
                $link->maintenance_procedure_id = MaintenanceProcedure::factory()->create([
                    'company_id' => $link->company_id,
                ])->id;
            }
        });
    }
}
