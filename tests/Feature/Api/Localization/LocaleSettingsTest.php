<?php

use function Pest\Laravel\assertDatabaseHas;

it('returns default locale settings for a company', function (): void {
    $user = createLocalizationFeatureUser();

    $response = $this->getJson('/api/settings/localization');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.locale', 'en-US')
        ->assertJsonPath('data.timezone', 'UTC')
        ->assertJsonPath('data.number_format', '1,234.56')
        ->assertJsonPath('data.date_format', 'YYYY-MM-DD')
        ->assertJsonPath('data.currency.primary', 'USD')
        ->assertJsonPath('data.currency.display_fx', false)
        ->assertJsonPath('data.uom.base_uom', 'EA');

    $this->assertDatabaseHas('company_locale_settings', [
        'company_id' => $user->company_id,
        'locale' => 'en-US',
        'currency_primary' => 'USD',
        'uom_base' => 'EA',
    ]);
});

it('updates locale settings', function (): void {
    $user = createLocalizationFeatureUser();

    $payload = [
        'locale' => 'de-DE',
        'timezone' => 'Europe/Berlin',
        'number_format' => '1.234,56',
        'date_format' => 'DD/MM/YYYY',
        'currency' => [
            'primary' => 'EUR',
            'display_fx' => true,
        ],
        'uom' => [
            'base_uom' => 'EA',
            'maps' => ['PACK' => 'EA'],
        ],
    ];

    $response = $this->patchJson('/api/settings/localization', $payload);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Locale settings updated.')
        ->assertJsonPath('data.locale', 'de-DE')
        ->assertJsonPath('data.timezone', 'Europe/Berlin')
        ->assertJsonPath('data.currency.primary', 'EUR')
        ->assertJsonPath('data.currency.display_fx', true)
        ->assertJsonPath('data.uom.maps.PACK', 'EA');

    $this->assertDatabaseHas('company_locale_settings', [
        'company_id' => $user->company_id,
        'locale' => 'de-DE',
        'timezone' => 'Europe/Berlin',
        'number_format' => '1.234,56',
        'date_format' => 'DD/MM/YYYY',
        'currency_primary' => 'EUR',
        'currency_display_fx' => true,
    ]);
});

it('requires plan access for localization endpoints', function (): void {
    $user = createLocalizationFeatureUser(planOverrides: ['localization_enabled' => false]);

    $response = $this->getJson('/api/settings/localization');

    $response->assertStatus(402)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Upgrade required.');
});
