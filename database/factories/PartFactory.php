<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Part>
 */
class PartFactory extends Factory
{
    protected $model = Part::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'part_number' => 'PN-'.strtoupper(Str::random(6)),
            'name' => $this->faker->words(3, true),
            'uom' => 'ea',
            'spec' => $this->faker->optional()->paragraph(),
            'meta' => [],
        ];
    }
}
