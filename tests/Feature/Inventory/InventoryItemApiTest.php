<?php

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\InventorySetting;
use App\Models\Part;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

it('creates an inventory item with reorder settings', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create(['role' => 'buyer_admin']);

    actingAs($user);

    $response = postJson('/api/inventory/items', [
        'sku' => 'ITEM-001',
        'name' => 'Precision Gear',
        'uom' => 'EA',
        'category' => 'Powertrain',
        'description' => 'High torque gear',
        'default_location_id' => 'WH-A1',
        'attributes' => [
            'finish' => 'anodized',
            'weight' => 2.5,
        ],
        'min_stock' => 5.25,
        'reorder_qty' => 10,
        'lead_time_days' => 14,
        'active' => true,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.item.sku', 'ITEM-001')
        ->assertJsonPath('data.item.min_stock', 5.25)
        ->assertJsonPath('message', 'Inventory item created.');

    $part = Part::query()
        ->where('company_id', $company->id)
        ->where('part_number', 'ITEM-001')
        ->first();

    expect($part)->not->toBeNull();
    expect($part?->default_location_code)->toBe('WH-A1');
    expect($part?->attributes)->toMatchArray([
        'finish' => 'anodized',
        'weight' => 2.5,
    ]);

    $settings = $part?->inventorySetting;
    expect($settings)->not->toBeNull();
    expect((float) $settings?->min_qty)->toBe(5.25);
    expect((float) $settings?->reorder_qty)->toBe(10.0);
    expect($settings?->lead_time_days)->toBe(14);

    $auditExists = AuditLog::query()
        ->where('entity_type', $part?->getMorphClass())
        ->where('entity_id', $part?->getKey())
        ->where('action', 'created')
        ->exists();

    expect($auditExists)->toBeTrue();
});

it('updates an inventory item and synchronises reorder data', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create(['role' => 'buyer_admin']);

    $part = Part::factory()
        ->for($company)
        ->create([
            'part_number' => 'ITEM-002',
            'name' => 'Legacy Valve',
            'uom' => 'EA',
            'category' => 'Legacy',
            'attributes' => ['finish' => 'raw'],
        ]);

    InventorySetting::query()->create([
        'company_id' => $company->id,
        'part_id' => $part->id,
        'min_qty' => 1,
        'reorder_qty' => 3,
        'lead_time_days' => 5,
    ]);

    actingAs($user);

    $response = patchJson("/api/inventory/items/{$part->id}", [
        'sku' => 'ITEM-002-REV2',
        'name' => 'Valve Assembly',
        'category' => null,
        'attributes' => [
            'finish' => 'powder',
            'weight' => 1.2,
        ],
        'min_stock' => 2,
        'reorder_qty' => 6.5,
        'lead_time_days' => 3,
        'active' => false,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.item.sku', 'ITEM-002-REV2')
        ->assertJsonPath('data.item.active', false)
        ->assertJsonPath('data.item.min_stock', 2)
        ->assertJsonPath('message', 'Inventory item updated.');

    $part->refresh();
    expect($part->part_number)->toBe('ITEM-002-REV2');
    expect($part->name)->toBe('Valve Assembly');
    expect($part->category)->toBeNull();
    expect($part->active)->toBeFalse();
    expect($part->attributes)->toMatchArray([
        'finish' => 'powder',
        'weight' => 1.2,
    ]);

    $settings = $part->inventorySetting;
    expect($settings)->not->toBeNull();
    expect((float) $settings?->min_qty)->toBe(2.0);
    expect((float) $settings?->reorder_qty)->toBe(6.5);
    expect($settings?->lead_time_days)->toBe(3);

    $auditExists = AuditLog::query()
        ->where('entity_type', $part->getMorphClass())
        ->where('entity_id', $part->id)
        ->where('action', 'updated')
        ->exists();

    expect($auditExists)->toBeTrue();
});

it('blocks creation when the user lacks inventory permissions', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create(['role' => 'buyer_requester']);

    actingAs($user);

    postJson('/api/inventory/items', [
        'sku' => 'ITEM-003',
        'name' => 'Washer',
        'uom' => 'EA',
    ])->assertStatus(403);
});

it('returns not found when updating a missing inventory item', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create(['role' => 'buyer_admin']);

    actingAs($user);

    patchJson('/api/inventory/items/999999', [
        'name' => 'Ghost Item',
    ])->assertStatus(404);
});

it('shows a single inventory item', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create(['role' => 'buyer_admin']);

    $part = Part::factory()
        ->for($company)
        ->create([
            'part_number' => 'ITEM-004',
            'name' => 'Sensor',
            'uom' => 'EA',
        ]);

    InventorySetting::query()->create([
        'company_id' => $company->id,
        'part_id' => $part->id,
        'min_qty' => 4,
    ]);

    actingAs($user);

    getJson("/api/inventory/items/{$part->id}")
        ->assertOk()
        ->assertJsonPath('data.item.sku', 'ITEM-004')
        ->assertJsonPath('data.item.min_stock', 4);
});
