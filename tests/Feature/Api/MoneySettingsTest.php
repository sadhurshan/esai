<?php

use Database\Seeders\CurrenciesSeeder;
use function Pest\Laravel\seed;

it('returns default money settings when none exist', function (): void {
    seed(CurrenciesSeeder::class);

    createMoneyFeatureUser(role: 'buyer_admin');

    $response = $this->getJson('/api/money/settings');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.base_currency.code', 'USD')
        ->assertJsonPath('data.pricing_currency.code', 'USD')
        ->assertJsonPath('data.fx_source', 'manual')
        ->assertJsonPath('data.tax_regime', 'exclusive');
});

it('updates money settings', function (): void {
    seed(CurrenciesSeeder::class);

    $user = createMoneyFeatureUser(role: 'buyer_admin');

    $payload = [
        'base_currency' => 'USD',
        'pricing_currency' => 'EUR',
        'fx_source' => 'manual',
        'price_round_rule' => 'bankers',
        'tax_regime' => 'inclusive',
        'defaults_meta' => ['default_tax_code_id' => 12],
    ];

    $response = $this->putJson('/api/money/settings', $payload);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Money settings updated.')
        ->assertJsonPath('data.price_round_rule', 'bankers')
        ->assertJsonPath('data.tax_regime', 'inclusive')
        ->assertJsonPath('data.pricing_currency.code', 'EUR')
        ->assertJsonPath('data.defaults.default_tax_code_id', 12);

    $this->assertDatabaseHas('company_money_settings', [
        'company_id' => $user->company_id,
        'base_currency' => 'USD',
        'pricing_currency' => 'EUR',
        'tax_regime' => 'inclusive',
    ]);
});

it('rejects money settings updates from unauthorized roles', function (): void {
    seed(CurrenciesSeeder::class);

    createMoneyFeatureUser(role: 'finance');

    $response = $this->putJson('/api/money/settings', [
        'base_currency' => 'USD',
        'pricing_currency' => 'USD',
        'fx_source' => 'manual',
        'price_round_rule' => 'half_up',
        'tax_regime' => 'exclusive',
    ]);

    $response->assertStatus(403);
});

it('returns 402 for money settings when plan gating is disabled', function (): void {
    createMoneyFeatureUser(
        planOverrides: [
            'multi_currency_enabled' => false,
            'tax_engine_enabled' => false,
        ]
    );

    $response = $this->getJson('/api/money/settings');

    $response->assertStatus(402)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Upgrade required.');
});
