<?php

use App\Models\FxRate;
use Database\Seeders\CurrenciesSeeder;
use Illuminate\Support\Facades\Cache;
use function Pest\Laravel\seed;

beforeEach(function (): void {
    seed(CurrenciesSeeder::class);
    Cache::clear();
});

it('lists fx rates with filters', function (): void {
    createMoneyFeatureUser(role: 'owner');

    FxRate::create([
        'base_code' => 'USD',
        'quote_code' => 'EUR',
        'rate' => '1.35000000',
        'as_of' => '2024-01-01',
    ]);

    FxRate::create([
        'base_code' => 'EUR',
        'quote_code' => 'USD',
        'rate' => '0.95000000',
        'as_of' => '2023-12-15',
    ]);

    $response = $this->getJson('/api/money/fx?base_code=USD');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.base_code', 'USD')
        ->assertJsonPath('data.items.0.quote_code', 'EUR')
        ->assertJsonPath('data.items.0.rate', '1.35000000');
});

it('upserts fx rates', function (): void {
    $user = createMoneyFeatureUser(role: 'owner');

    $payload = [
        'rates' => [
            [
                'base_code' => 'USD',
                'quote_code' => 'EUR',
                'rate' => '0.92000000',
                'as_of' => '2024-02-01',
            ],
        ],
    ];

    $response = $this->postJson('/api/money/fx', $payload);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'FX rates updated.')
        ->assertJsonCount(1, 'data.items');

    $this->assertDatabaseHas('fx_rates', [
        'base_code' => 'USD',
        'quote_code' => 'EUR',
        'as_of' => '2024-02-01 00:00:00',
        'rate' => 0.92,
    ]);

    $updateResponse = $this->postJson('/api/money/fx', [
        'rates' => [
            [
                'base_code' => 'USD',
                'quote_code' => 'EUR',
                'rate' => '0.95000000',
                'as_of' => '2024-02-01',
            ],
        ],
    ]);

    $updateResponse->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.items.0.rate', '0.95000000');

    $this->assertDatabaseHas('fx_rates', [
        'base_code' => 'USD',
        'quote_code' => 'EUR',
        'as_of' => '2024-02-01 00:00:00',
        'rate' => 0.95,
    ]);
});

it('enforces billing permissions for fx endpoints', function (): void {
    createMoneyFeatureUser(role: 'buyer_member');

    $response = $this->getJson('/api/money/fx');

    $response->assertStatus(403)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Billing permissions required.');

    $postResponse = $this->postJson('/api/money/fx', [
        'rates' => [
            [
                'base_code' => 'USD',
                'quote_code' => 'EUR',
                'rate' => '0.90000000',
                'as_of' => '2024-03-01',
            ],
        ],
    ]);

    $postResponse->assertStatus(403)
        ->assertJsonPath('message', 'Billing permissions required.');
});

it('allows finance roles to manage fx rates', function (): void {
    createMoneyFeatureUser(role: 'finance');

    FxRate::create([
        'base_code' => 'USD',
        'quote_code' => 'EUR',
        'rate' => '1.10000000',
        'as_of' => '2024-01-15',
    ]);

    $this->getJson('/api/money/fx')
        ->assertOk()
        ->assertJsonPath('data.items.0.base_code', 'USD');

    $payload = [
        'rates' => [
            [
                'base_code' => 'USD',
                'quote_code' => 'CAD',
                'rate' => '1.30000000',
                'as_of' => '2024-03-15',
            ],
        ],
    ];

    $this->postJson('/api/money/fx', $payload)
        ->assertOk()
        ->assertJsonPath('message', 'FX rates updated.');

    $this->assertDatabaseHas('fx_rates', [
        'base_code' => 'USD',
        'quote_code' => 'CAD',
        'as_of' => '2024-03-15 00:00:00',
    ]);
});

it('returns validation errors when base and quote currencies match', function (): void {
    createMoneyFeatureUser(role: 'owner');

    $response = $this->postJson('/api/money/fx', [
        'rates' => [
            [
                'base_code' => 'USD',
                'quote_code' => 'USD',
                'rate' => '1.00000000',
                'as_of' => '2024-02-01',
            ],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['rates.0.quote_code']);

    $errors = $response->json('errors');
    expect($errors['rates.0.quote_code'][0] ?? null)->toBe('Quote currency must differ from base currency.');
});

it('returns 402 when the plan does not include money features', function (): void {
    createMoneyFeatureUser(
        planOverrides: [
            'multi_currency_enabled' => false,
            'tax_engine_enabled' => false,
        ]
    );

    $response = $this->getJson('/api/money/fx');

    $response->assertStatus(402)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Upgrade required.');
});
