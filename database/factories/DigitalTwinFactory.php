<?php

namespace Database\Factories;

use App\Enums\DigitalTwinStatus;
use App\Models\Company;
use App\Models\DigitalTwin;
use App\Models\DigitalTwinCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DigitalTwin>
 */
class DigitalTwinFactory extends Factory
{
    protected $model = DigitalTwin::class;

    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(3);

        return [
            'company_id' => Company::factory(),
            'category_id' => DigitalTwinCategory::factory(),
            'slug' => Str::slug($title.'-'.$this->faker->unique()->numerify('###')),
            'code' => strtoupper(Str::random(8)),
            'title' => $title,
            'summary' => $this->faker->paragraph(),
            'status' => DigitalTwinStatus::Draft,
            'version' => '1.0.0',
            'tags' => $this->faker->randomElements(['cnc', 'aerospace', 'automotive', 'precision'], 2),
            'thumbnail_path' => null,
            'visibility' => 'public',
        ];
    }

    public function published(): self
    {
        return $this->state(fn () => [
            'status' => DigitalTwinStatus::Published,
            'published_at' => now(),
        ]);
    }
}
