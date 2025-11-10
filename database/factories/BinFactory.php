<?php

namespace Database\Factories;

use App\Models\Bin;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Bin>
 */
class BinFactory extends Factory
{
    protected $model = Bin::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'warehouse_id' => Warehouse::factory(),
            'code' => strtoupper(Str::random(6)),
            'name' => 'Bin '.$this->faker->randomLetter().$this->faker->numberBetween(1, 20),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Bin $bin): void {
            if ($bin->company_id === null && $bin->warehouse) {
                $bin->company_id = $bin->warehouse->company_id;
                $bin->save();
            }
        });
    }
}
