<?php

use App\Models\Company;
use App\Models\CompanyFeatureFlag;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;

it('returns envelope errors when feature flags are requested for the wrong company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    $flag = CompanyFeatureFlag::query()->create([
        'company_id' => $otherCompany->id,
        'key' => 'beta_feature',
        'value' => ['enabled' => true],
    ]);

    $admin = User::factory()->create([
        'role' => 'platform_super',
    ]);

    actingAs($admin);

    deleteJson("/api/admin/companies/{$company->id}/feature-flags/{$flag->id}")
        ->assertStatus(404)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Feature flag not found for this company.');
});
