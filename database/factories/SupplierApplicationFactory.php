<?php

namespace Database\Factories;

use App\Enums\SupplierApplicationStatus;
use App\Models\Company;
use App\Models\SupplierApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierApplication>
 */
class SupplierApplicationFactory extends Factory
{
    protected $model = SupplierApplication::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'submitted_by' => User::factory(),
            'status' => SupplierApplicationStatus::Pending,
            'form_json' => [
                'capabilities' => $this->faker->words(3),
                'materials' => $this->faker->words(2),
                'certifications' => [$this->faker->word()],
                'facilities' => $this->faker->sentence(),
            ],
            'notes' => null,
        ];
    }

    public function approved(): self
    {
        return $this->state(fn (): array => [
            'status' => SupplierApplicationStatus::Approved,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): self
    {
        return $this->state(fn (): array => [
            'status' => SupplierApplicationStatus::Rejected,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
            'notes' => $this->faker->sentence(),
        ]);
    }
}
