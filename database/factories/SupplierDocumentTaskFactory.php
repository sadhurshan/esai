<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\SupplierDocumentTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierDocumentTask>
 */
class SupplierDocumentTaskFactory extends Factory
{
    protected $model = SupplierDocumentTask::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'supplier_id' => Supplier::factory(),
            'document_type' => $this->faker->randomElement(SupplierDocument::DOCUMENT_TYPES),
            'status' => SupplierDocumentTask::STATUS_PENDING,
            'is_required' => true,
            'priority' => $this->faker->numberBetween(1, 5),
            'due_at' => $this->faker->optional()->dateTimeBetween('+2 days', '+45 days'),
            'description' => $this->faker->sentence(8),
            'notes' => $this->faker->optional()->paragraph(),
        ];
    }

    public function fulfilled(): self
    {
        return $this->state(function (): array {
            return [
                'status' => SupplierDocumentTask::STATUS_FULFILLED,
                'completed_at' => now(),
            ];
        });
    }
}
