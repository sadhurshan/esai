<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierRiskScore;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierRiskScoreFactory extends Factory
{
    protected $model = SupplierRiskScore::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'supplier_id' => Supplier::factory(),
            'on_time_delivery_rate' => $this->faker->randomFloat(2, 0.7, 1.0),
            'defect_rate' => $this->faker->randomFloat(2, 0, 0.1),
            'price_volatility' => $this->faker->randomFloat(4, 0, 0.2),
            'lead_time_volatility' => $this->faker->randomFloat(4, 0, 5),
            'responsiveness_rate' => $this->faker->randomFloat(2, 0, 1),
            'overall_score' => $this->faker->randomFloat(4, 0, 1),
            'risk_grade' => $this->faker->randomElement(['low', 'medium', 'high']),
            'badges_json' => null,
            'meta' => null,
        ];
    }
}
