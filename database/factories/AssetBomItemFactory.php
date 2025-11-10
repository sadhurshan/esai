<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetBomItem;
use App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetBomItem>
 */
class AssetBomItemFactory extends Factory
{
    protected $model = AssetBomItem::class;

    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'part_id' => Part::factory(),
            'quantity' => $this->faker->randomFloat(3, 1, 25),
            'uom' => 'ea',
            'criticality' => $this->faker->randomElement(['low', 'medium', 'high']),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
