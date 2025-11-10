<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Location;
use App\Models\System;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<System>
 */
class SystemFactory extends Factory
{
    protected $model = System::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'location_id' => Location::factory(),
            'name' => $this->faker->words(3, true),
            'code' => strtoupper(Str::random(6)),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
