<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Company;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'location_id' => null,
            'system_id' => null,
            'name' => $this->faker->words(2, true).' Asset',
            'tag' => 'AS-'.strtoupper(Str::random(6)),
            'serial_no' => $this->faker->optional()->bothify('SN-#####'),
            'model_no' => $this->faker->optional()->bothify('MD-###'),
            'manufacturer' => $this->faker->company,
            'commissioned_at' => $this->faker->optional()->date(),
            'status' => 'active',
            'meta' => [],
        ];
    }

    public function configure(): self
    {
        return $this->afterMaking(function (Asset $asset): void {
            if ($asset->company_id === null) {
                $asset->company_id = Company::factory()->create()->id;
            }

            if ($asset->location_id === null) {
                $asset->location_id = Location::factory()->create([
                    'company_id' => $asset->company_id,
                ])->id;
            }
        })->afterCreating(function (Asset $asset): void {
            if ($asset->company_id === null) {
                $asset->company_id = Company::factory()->create()->id;
                $asset->save();
            }

            if ($asset->location_id === null) {
                $asset->location_id = Location::factory()->create([
                    'company_id' => $asset->company_id,
                ])->id;
                $asset->save();
            }
        });
    }
}
