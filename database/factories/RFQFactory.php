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

        $methods = [
            'CNC Milling',
            'CNC Turning',
            'Sheet Metal',
            'Injection Molding',
            '3D Printing',
        ];

        $statuses = ['awaiting', 'open', 'closed', 'awarded', 'cancelled'];
        $status = $this->faker->randomElement($statuses);

        $sentAt = $status === 'awaiting'
            ? null
            : $this->faker->dateTimeBetween('-60 days', 'now');

        $deadlineAt = match ($status) {
            'awaiting', 'open' => $this->faker->dateTimeBetween('+5 days', '+75 days'),
            'closed', 'awarded' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
            default => $this->faker->dateTimeBetween('-10 days', '+45 days'),
        };

        $tolerances = ['±0.005"', '±0.010"', '±0.25mm'];
        $finishes = ['Anodized', 'Powder Coat', 'Bead Blast', 'Polished'];

        return [
            'number' => sprintf('%05d', $this->faker->unique()->numberBetween(0, 99999)),
            'item_name' => ucfirst($this->faker->words($this->faker->numberBetween(2, 4), true)),
            'type' => $this->faker->randomElement(['ready_made', 'manufacture']),
            'quantity' => $this->faker->numberBetween(10, 500),
            'material' => $this->faker->randomElement($materials),
            'method' => $this->faker->randomElement($methods),
            'tolerance' => $this->faker->optional(0.6)->randomElement($tolerances),
            'finish' => $this->faker->optional(0.5)->randomElement($finishes),
            'client_company' => $this->faker->company(),
            'status' => $status,
            'deadline_at' => $deadlineAt,
            'sent_at' => $sentAt,
            'is_open_bidding' => $status === 'open' ? $this->faker->boolean(70) : false,
            'notes' => $this->faker->optional(0.4)->sentences($this->faker->numberBetween(1, 2), true),
            'cad_path' => $this->faker->optional(0.3)->randomElement([
                'cad/sample-bracket.step',
                'cad/assembly-v1.igs',
                'cad/demo-plate.stp',
            ]),
        ];
    }
}
