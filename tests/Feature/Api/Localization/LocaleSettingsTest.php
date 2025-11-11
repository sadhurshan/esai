<?php

use function Pest\Laravel\assertDatabaseHas;

it('returns default locale settings for a company', function (): void {
    $user = createLocalizationFeatureUser();

    $response = $this->getJson('/api/localization/settings');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.locale', 'en')
        ->assertJsonPath('data.timezone', 'UTC')
        ->assertJsonPath('data.number_format', 'system')
        ->assertJsonPath('data.date_format', 'system')
        ->assertJsonPath('data.first_day_of_week', 1)
        ->assertJsonPath('data.weekend_days', [6, 0]);

    $this->assertDatabaseHas('company_locale_settings', [
        'company_id' => $user->company_id,
        'locale' => 'en',
    ]);
});

it('updates locale settings', function (): void {
    $user = createLocalizationFeatureUser();

    $payload = [
        'locale' => 'de',
        'timezone' => 'Europe/Berlin',
        'number_format' => 'de-DE',
        'date_format' => 'DMY',
        'first_day_of_week' => 1,
        'weekend_days' => [6],
    ];

    $response = $this->putJson('/api/localization/settings', $payload);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Locale settings updated.')
        ->assertJsonPath('data.locale', 'de')
        ->assertJsonPath('data.timezone', 'Europe/Berlin')
        ->assertJsonPath('data.number_format', 'de-DE')
        ->assertJsonPath('data.weekend_days', [6]);

    $this->assertDatabaseHas('company_locale_settings', [
        'company_id' => $user->company_id,
        'locale' => 'de',
        'timezone' => 'Europe/Berlin',
        'number_format' => 'de-DE',
        'date_format' => 'DMY',
        'first_day_of_week' => 1,
    ]);
});

it('requires plan access for localization endpoints', function (): void {
    $user = createLocalizationFeatureUser(planOverrides: ['localization_enabled' => false]);

    $response = $this->getJson('/api/localization/settings');

    $response->assertStatus(402)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Upgrade required.');
});
