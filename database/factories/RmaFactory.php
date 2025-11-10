<?php

namespace Database\Factories;

use App\Enums\RmaStatus;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Rma;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RmaFactory extends Factory
{
    protected $model = Rma::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'purchase_order_id' => PurchaseOrder::factory()->state([
                'status' => 'confirmed',
            ]),
            'submitted_by' => User::factory(),
            'reason' => $this->faker->sentence(6),
            'description' => $this->faker->paragraph(),
            'resolution_requested' => $this->faker->randomElement(['repair', 'replacement', 'credit', 'refund', 'other']),
            'status' => RmaStatus::Raised,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Rma $rma): void {
            $companyId = $rma->company_id;

            PurchaseOrder::whereKey($rma->purchase_order_id)
                ->where('company_id', '!=', $companyId)
                ->update(['company_id' => $companyId]);

            User::whereKey($rma->submitted_by)
                ->where('company_id', '!=', $companyId)
                ->update(['company_id' => $companyId]);
        });
    }
}
