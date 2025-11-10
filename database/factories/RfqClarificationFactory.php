<?php

namespace Database\Factories;

use App\Enums\RfqClarificationType;
use App\Models\Company;
use App\Models\RFQ;
use App\Models\RfqClarification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfqClarification>
 */
class RfqClarificationFactory extends Factory
{
    protected $model = RfqClarification::class;

    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'rfq_id' => RFQ::factory()->for($company),
            'user_id' => User::factory()->for($company),
            'type' => RfqClarificationType::Question,
            'message' => $this->faker->sentences(2, true),
            'attachments_json' => [],
            'version_increment' => false,
            'version_no' => null,
        ];
    }

    public function amendment(int $versionNo = 2): self
    {
        return $this->state(fn (): array => [
            'type' => RfqClarificationType::Amendment,
            'version_increment' => true,
            'version_no' => $versionNo,
        ]);
    }
}
