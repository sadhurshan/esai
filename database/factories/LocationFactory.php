<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->city.' Plant',
            'code' => strtoupper(Str::random(6)),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
