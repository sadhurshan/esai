<?php

namespace Database\Factories;

use App\Models\RFQ;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RFQ>
 */
class RFQFactory extends Factory
{
    protected $model = RFQ::class;

    public function definition(): array
    {
        $materials = [
            'Aluminum',
            'Stainless Steel',
            'Mild Steel',
            'ABS',
            'Nylon',
            'Brass',
            'Copper',
        ];

        $statuses = RFQ::STATUSES;
        $status = $this->faker->randomElement($statuses);

        $publishAt = $status === RFQ::STATUS_DRAFT
            ? null
            : $this->faker->dateTimeBetween('-60 days', 'now');

        $dueAt = match ($status) {
            RFQ::STATUS_DRAFT => $this->faker->dateTimeBetween('+5 days', '+90 days'),
            RFQ::STATUS_OPEN => $this->faker->dateTimeBetween('+2 days', '+45 days'),
            RFQ::STATUS_CLOSED, RFQ::STATUS_AWARDED => $this->faker->dateTimeBetween('-30 days', '-1 day'),
            RFQ::STATUS_CANCELLED => $this->faker->dateTimeBetween('-15 days', '+15 days'),
            default => $this->faker->dateTimeBetween('-10 days', '+45 days'),
        };

        $tolerances = ['±0.005"', '±0.010"', '±0.25mm'];
        $finishes = ['Anodized', 'Powder Coat', 'Bead Blast', 'Polished'];

        return [
            'number' => sprintf('%05d', $this->faker->unique()->numberBetween(0, 99999)),
            'title' => ucfirst($this->faker->words($this->faker->numberBetween(2, 4), true)),
            'method' => $this->faker->randomElement(RFQ::METHODS),
            'material' => $this->faker->randomElement($materials),
            'tolerance' => $this->faker->optional(0.6)->randomElement($tolerances),
            'finish' => $this->faker->optional(0.5)->randomElement($finishes),
            'quantity_total' => $this->faker->numberBetween(10, 500),
            'delivery_location' => $this->faker->city(),
            'incoterm' => $this->faker->randomElement(['FOB', 'CIF', 'DAP', 'DDP']),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'GBP']),
            'status' => $status,
            'publish_at' => $publishAt,
            'due_at' => $dueAt,
            'close_at' => $dueAt,
            'open_bidding' => $status === RFQ::STATUS_OPEN ? $this->faker->boolean(70) : false,
            'notes' => $this->faker->optional(0.4)->sentences($this->faker->numberBetween(1, 2), true),
            'cad_document_id' => null,
            'rfq_version' => 1,
            'attachments_count' => 0,
            'meta' => [],
        ];
    }
}
