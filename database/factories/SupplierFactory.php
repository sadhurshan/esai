<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        $capabilities = [
            'methods' => $this->faker->randomElements([
                'CNC Milling',
                'CNC Turning',
                'Sheet Metal Fabrication',
                'Injection Molding',
                'Additive Manufacturing',
                'Die Casting',
                'Waterjet Cutting',
            ], $this->faker->numberBetween(2, 5)),
            'materials' => $this->faker->randomElements([
                'Aluminum 6061',
                'Aluminum 7075',
                'Stainless Steel 304',
                'Stainless Steel 316',
                'Mild Steel',
                'ABS',
                'PA12',
                'PEEK',
                'Copper',
                'Titanium',
            ], $this->faker->numberBetween(3, 6)),
            'tolerances' => $this->faker->randomElements([
                '+/- 0.010"',
                '+/- 0.005"',
                '+/- 0.002"',
                'ISO 2768-m',
                'ISO 2768-f',
            ], $this->faker->numberBetween(1, 3)),
            'finishes' => $this->faker->randomElements([
                'Anodizing',
                'Powder Coat',
                'Black Oxide',
                'Passivation',
                'Polishing',
            ], $this->faker->numberBetween(1, 3)),
            'industries' => $this->faker->randomElements([
                'Aerospace',
                'Automotive',
                'Medical',
                'Industrial Equipment',
                'Consumer Electronics',
                'Robotics',
            ], $this->faker->numberBetween(2, 4)),
        ];

        $country = $this->faker->countryCode();
        $city = $this->faker->city();

        return [
            'name' => $this->faker->unique()->company(),
            'capabilities' => $capabilities,
            'email' => $this->faker->unique()->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'website' => $this->faker->url(),
            'address' => $this->faker->streetAddress(),
            'country' => strtoupper($country),
            'city' => $city,
            'status' => 'pending',
            'geo_lat' => $this->faker->latitude(),
            'geo_lng' => $this->faker->longitude(),
            'lead_time_days' => $this->faker->numberBetween(5, 45),
            'moq' => $this->faker->numberBetween(1, 500),
            'rating_avg' => $this->faker->randomFloat(2, 0, 5),
            'risk_grade' => null,
            'payment_terms' => $this->faker->randomElement(['Net 30', 'Net 45', 'Net 60']),
            'tax_id' => strtoupper($this->faker->bothify('SUP-####')),
            'onboarding_notes' => $this->faker->optional()->sentence(10),
        ];
    }
}
