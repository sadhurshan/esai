<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Part;
use App\Models\PartTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartTag>
 */
class PartTagFactory extends Factory
{
    protected $model = PartTag::class;

    public function definition(): array
    {
        $tag = $this->faker->unique()->words(2, true);

        return [
            'company_id' => Company::factory(),
            'part_id' => Part::factory(),
            'tag' => $tag,
            'normalized_tag' => strtolower($tag),
        ];
    }
}
