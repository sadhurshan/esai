<?php

use function Pest\Laravel\assertDatabaseHas;

it('returns company profile defaults', function (): void {
    $user = createLocalizationFeatureUser();

    $response = $this->getJson('/api/settings/company');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure(['data' => ['legal_name', 'display_name', 'emails', 'phones']]);

    assertDatabaseHas('company_profiles', [
        'company_id' => $user->company_id,
        'legal_name' => $user->company->name,
    ]);
});

it('updates company profile details', function (): void {
    $user = createLocalizationFeatureUser();

    $payload = [
        'legal_name' => 'Elements Supply Holdings',
        'display_name' => 'Elements Supply',
        'tax_id' => '99-1234567',
        'registration_number' => 'REG-123',
        'emails' => ['ops@example.com'],
        'phones' => ['+1-555-1000'],
        'bill_to' => [
            'attention' => 'AP',
            'line1' => '1 Market St',
            'country' => 'US',
        ],
        'ship_from' => [
            'line1' => 'Warehouse Rd',
            'country' => 'US',
        ],
    ];

    $response = $this->patchJson('/api/settings/company', $payload);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Company settings updated.')
        ->assertJsonPath('data.legal_name', 'Elements Supply Holdings')
        ->assertJsonPath('data.bill_to.country', 'US');

    assertDatabaseHas('company_profiles', [
        'company_id' => $user->company_id,
        'legal_name' => 'Elements Supply Holdings',
        'tax_id' => '99-1234567',
    ]);
});
