<?php

use App\Models\TaxCode;

it('lists tax codes for the company', function (): void {
    $user = createMoneyFeatureUser();
    $company = $user->company;

    TaxCode::factory()->for($company)->create([
        'code' => 'GST01',
        'name' => 'GST Standard',
        'type' => 'gst',
        'rate_percent' => 5.0,
        'active' => true,
    ]);

    TaxCode::factory()->for($company)->create([
        'code' => 'VAT20',
        'name' => 'VAT Reduced',
        'type' => 'vat',
        'rate_percent' => 20.0,
        'active' => false,
    ]);

    TaxCode::factory()->create();

    $response = $this->getJson('/api/money/tax-codes');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.items.0.code', 'GST01')
        ->assertJsonPath('data.items.1.code', 'VAT20')
        ->assertJsonPath('meta.next_cursor', null)
        ->assertJsonPath('meta.prev_cursor', null);

    $activeResponse = $this->getJson('/api/money/tax-codes?active=1');

    $activeResponse->assertOk()
        ->assertJsonPath('data.items.0.code', 'GST01')
        ->assertJsonCount(1, 'data.items');
});

it('creates a tax code and returns the resource envelope', function (): void {
    $user = createMoneyFeatureUser();

    $payload = [
        'code' => 'HSN123',
        'name' => 'Harmonized Tax',
        'type' => 'custom',
        'rate_percent' => 7.5,
        'is_compound' => true,
        'meta' => ['jurisdiction' => 'CA'],
    ];

    $response = $this->postJson('/api/money/tax-codes', $payload);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.code', 'HSN123')
        ->assertJsonPath('data.is_compound', true);

    $this->assertDatabaseHas('tax_codes', [
        'company_id' => $user->company_id,
        'code' => 'HSN123',
        'name' => 'Harmonized Tax',
        'is_compound' => true,
    ]);
});

it('updates an existing tax code', function (): void {
    $user = createMoneyFeatureUser();
    $company = $user->company;

    $taxCode = TaxCode::factory()->for($company)->create([
        'code' => 'VAT10',
        'name' => 'Legacy VAT',
        'type' => 'vat',
        'rate_percent' => 10.0,
        'is_compound' => false,
        'active' => true,
        'meta' => ['jurisdiction' => 'EU'],
    ]);

    $payload = [
        'code' => 'VAT15',
        'name' => 'VAT Updated',
        'type' => 'vat',
        'rate_percent' => 15.0,
        'is_compound' => true,
        'active' => false,
        'meta' => ['jurisdiction' => 'EU', 'notes' => 'Updated rate'],
    ];

    $response = $this->putJson("/api/money/tax-codes/{$taxCode->id}", $payload);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.code', 'VAT15')
        ->assertJsonPath('data.active', false)
        ->assertJsonPath('data.is_compound', true);

    $this->assertDatabaseHas('tax_codes', [
        'id' => $taxCode->id,
        'company_id' => $company->id,
        'code' => 'VAT15',
        'active' => false,
    ]);
});

it('soft deletes a tax code', function (): void {
    $user = createMoneyFeatureUser();
    $company = $user->company;

    $taxCode = TaxCode::factory()->for($company)->create([
        'code' => 'DEL01',
    ]);

    $response = $this->deleteJson("/api/money/tax-codes/{$taxCode->id}");

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Tax code removed.');

    $this->assertSoftDeleted('tax_codes', ['id' => $taxCode->id]);
});

it('blocks access when the plan does not include tax engine features', function (): void {
    $user = createMoneyFeatureUser([
        'multi_currency_enabled' => false,
        'tax_engine_enabled' => false,
    ]);

    $response = $this->getJson('/api/money/tax-codes');

    $response->assertStatus(402)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Upgrade required.');
});
