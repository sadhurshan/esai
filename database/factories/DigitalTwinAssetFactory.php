<?php

namespace Database\Factories;

use App\Enums\DigitalTwinAssetType;
use App\Models\DigitalTwin;
use App\Models\DigitalTwinAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DigitalTwinAsset>
 */
class DigitalTwinAssetFactory extends Factory
{
    protected $model = DigitalTwinAsset::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(DigitalTwinAssetType::cases());
        $filename = Str::slug($this->faker->words(2, true)).'.'.strtolower($type->value);

        return [
            'digital_twin_id' => DigitalTwin::factory(),
            'type' => $type,
            'disk' => 's3',
            'path' => 'digital-twins/'.$filename,
            'filename' => $filename,
            'size_bytes' => $this->faker->numberBetween(10_000, 5_000_000),
            'checksum' => Str::random(40),
            'mime' => $this->faker->mimeType(),
            'is_primary' => false,
            'meta' => ['notes' => $this->faker->sentence()],
        ];
    }
}
