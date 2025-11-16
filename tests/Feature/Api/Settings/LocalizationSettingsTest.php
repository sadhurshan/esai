<?php

use function Pest\Laravel\assertDatabaseHas;

it('returns localization defaults via settings endpoint', function (): void {
    $user = createLocalizationFeatureUser();

    $response = $this->getJson('/api/settings/localization');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.locale', 'en-US')
        ->assertJsonPath('data.timezone', 'UTC')
        ->assertJsonPath('data.date_format', 'YYYY-MM-DD')
        ->assertJsonPath('data.number_format', '1,234.56')
        ->assertJsonPath('data.currency.primary', 'USD')
        ->assertJsonPath('data.currency.display_fx', false)
        ->assertJsonPath('data.uom.base_uom', 'EA');

    assertDatabaseHas('company_locale_settings', [
        'company_id' => $user->company_id,
        'locale' => 'en-US',
        'timezone' => 'UTC',
        'number_format' => '1,234.56',
        'date_format' => 'YYYY-MM-DD',
        'currency_primary' => 'USD',
        'uom_base' => 'EA',
    ]);
});

it('updates localization preferences through settings endpoint', function (): void {
    $user = createLocalizationFeatureUser();

    $payload = [
        'locale' => 'de-DE',
        'timezone' => 'Europe/Berlin',
        'date_format' => 'DD/MM/YYYY',
        'number_format' => '1.234,56',
        'currency' => [
            'primary' => 'EUR',
            'display_fx' => true,
        ],
        'uom' => [
            'base_uom' => 'EA',
            'maps' => [
                'PK' => 'EA',
            ],
        ],
    ];

    $response = $this->patchJson('/api/settings/localization', $payload);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Locale settings updated.')
        ->assertJsonPath('data.locale', 'de-DE')
        ->assertJsonPath('data.timezone', 'Europe/Berlin')
        ->assertJsonPath('data.date_format', 'DD/MM/YYYY')
        ->assertJsonPath('data.number_format', '1.234,56')
        ->assertJsonPath('data.currency.primary', 'EUR')
        ->assertJsonPath('data.currency.display_fx', true)
        ->assertJsonPath('data.uom.maps.PK', 'EA');

    assertDatabaseHas('company_locale_settings', [
        'company_id' => $user->company_id,
        'locale' => 'de-DE',
        'timezone' => 'Europe/Berlin',
        'date_format' => 'DD/MM/YYYY',
        'number_format' => '1.234,56',
        'currency_primary' => 'EUR',
        'currency_display_fx' => true,
    ]);
});
