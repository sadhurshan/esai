<?php

use App\Models\Part;
use App\Models\Uom;
use Database\Seeders\UomSeeder;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\seed;

beforeEach(function (): void {
    seed(UomSeeder::class);
});

it('lists seeded units of measure', function (): void {
    createLocalizationFeatureUser();

    $response = $this->getJson('/api/localization/uoms');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.items.0.code', fn ($code) => is_string($code))
        ->assertJsonPath('data.meta.per_page', fn ($perPage) => $perPage >= 10)
        ->assertJsonPath('meta.cursor.has_next', fn ($hasNext) => is_bool($hasNext));
});

it('creates a new unit of measure', function (): void {
    createLocalizationFeatureUser();

    $payload = [
        'code' => 'bx',
        'name' => 'Box',
        'dimension' => 'count',
        'symbol' => 'bx',
        'si_base' => false,
    ];

    $response = $this->postJson('/api/localization/uoms', $payload);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.code', 'bx');

    assertDatabaseHas('uoms', [
        'code' => 'bx',
        'name' => 'Box',
    ]);
});

it('prevents deleting si base units', function (): void {
    createLocalizationFeatureUser();

    $kg = Uom::query()->where('code', 'kg')->firstOrFail();

    $response = $this->deleteJson("/api/localization/uoms/{$kg->id}");

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Cannot delete SI base units.');
});

it('converts quantities across multiple hops', function (): void {
    createLocalizationFeatureUser();

    $payload = [
        'qty' => 10,
        'from_code' => 'in',
        'to_code' => 'm',
    ];

    $response = $this->postJson('/api/localization/uom/convert', $payload);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.qty_converted', '0.254000');
});

it('rejects cross-dimension conversions', function (): void {
    createLocalizationFeatureUser();

    $payload = [
        'qty' => 5,
        'from_code' => 'kg',
        'to_code' => 'm',
    ];

    $response = $this->postJson('/api/localization/uom/convert', $payload);

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error');
});

it('converts quantities relative to a part base unit', function (): void {
    $user = createLocalizationFeatureUser();

    $kg = Uom::query()->where('code', 'kg')->firstOrFail();

    $part = Part::factory()->for($user->company)->create([
        'base_uom_id' => $kg->id,
        'uom' => 'kg',
    ]);

    $response = $this->getJson("/api/localization/parts/{$part->id}/convert?qty=10&from=lb&to=g");

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.base_uom', 'kg')
        ->assertJsonPath('data.base_qty', '4.535923')
        ->assertJsonPath('data.qty_converted', '4535.923000');
});
