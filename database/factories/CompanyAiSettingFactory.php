<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyAiSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyAiSetting>
 */
class CompanyAiSettingFactory extends Factory
{
    protected $model = CompanyAiSetting::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'llm_answers_enabled' => false,
            'llm_provider' => 'dummy',
        ];
    }
}
