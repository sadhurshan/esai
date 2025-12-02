<?php

namespace Database\Factories;

use App\Models\RFQ;
use App\Models\RfqItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfqItem>
 */
class RfqItemFactory extends Factory
{
    protected $model = RfqItem::class;

    public function configure(): static
    {
        return $this->afterMaking(function (RfqItem $item): void {
            $this->hydrateTenantMeta($item);
        })->afterCreating(function (RfqItem $item): void {
            if ($this->hydrateTenantMeta($item)) {
                $item->saveQuietly();
            }
        });
    }

    public function definition(): array
    {
        return [
            'rfq_id' => RFQ::factory(),
            'line_no' => $this->faker->unique()->numberBetween(1, 10),
            'part_number' => strtoupper($this->faker->bothify('PART-###')),
            'description' => $this->faker->sentence(),
            'method' => $this->faker->randomElement([
                'CNC Milling',
                'CNC Turning',
                'Sheet Metal',
                'Injection Molding',
                '3D Printing',
            ]),
            'material' => $this->faker->randomElement([
                'Aluminum 6061',
                'Stainless Steel 304',
                'ABS',
                'Brass',
                'Nylon',
            ]),
            'tolerance' => $this->faker->optional(0.5)->randomElement(['±0.005"', '±0.010"', '±0.25mm']),
            'finish' => $this->faker->optional(0.5)->randomElement(['Anodized', 'Powder Coat', 'Polished']),
            'qty' => $this->faker->numberBetween(1, 100),
            'uom' => 'pcs',
            'target_price' => $this->faker->randomFloat(2, 10, 500),
            'specs_json' => [
                'notes' => $this->faker->sentence(),
                'revision' => 1,
            ],
        ];
    }

    private function hydrateTenantMeta(RfqItem $item): bool
    {
        $rfq = $item->relationLoaded('rfq')
            ? $item->rfq
            : ($item->rfq_id ? RFQ::query()->find($item->rfq_id) : null);

        if (! $rfq) {
            return false;
        }

        $dirty = false;

        if ($item->company_id === null) {
            $item->company_id = $rfq->company_id;
            $dirty = true;
        }

        if ($item->created_by === null) {
            $item->created_by = $rfq->created_by;
            $dirty = true;
        }

        return $dirty;
    }
}
