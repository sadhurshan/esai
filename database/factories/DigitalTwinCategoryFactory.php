<?php

namespace Database\Factories;

use App\Models\DigitalTwinCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DigitalTwinCategory>
 */
class DigitalTwinCategoryFactory extends Factory
{
    protected $model = DigitalTwinCategory::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'slug' => Str::slug($name.'-'.$this->faker->unique()->numerify('###')),
            'name' => ucwords($name),
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }
}
