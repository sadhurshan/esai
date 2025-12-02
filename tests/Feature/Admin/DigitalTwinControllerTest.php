<?php

use App\Enums\DigitalTwinStatus;
use App\Models\DigitalTwin;
use App\Models\DigitalTwinCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('allows platform super admins to create digital twins', function () {
    $admin = User::factory()->create(['role' => 'platform_super']);
    $category = DigitalTwinCategory::factory()->create();

    actingAs($admin);

    $payload = [
        'category_id' => $category->id,
        'code' => 'DT-100',
        'title' => 'Hydraulic Valve Assembly',
        'summary' => 'Reference assembly for hydraulic subsystems.',
        'version' => '1.0.0',
        'tags' => ['hydraulic', 'valve'],
        'specs' => [
            ['name' => 'Material', 'value' => '316 Stainless'],
            ['name' => 'Pressure Rating', 'value' => '5000 PSI', 'uom' => 'psi'],
        ],
    ];

    $response = $this->postJson('/api/admin/digital-twins', $payload);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.digital_twin.title', 'Hydraulic Valve Assembly');

    $this->assertDatabaseHas('digital_twins', [
        'code' => 'DT-100',
        'title' => 'Hydraulic Valve Assembly',
    ]);

    $this->assertDatabaseHas('digital_twin_specs', [
        'name' => 'Material',
        'value' => '316 Stainless',
    ]);
});

it('blocks non platform users from creating digital twins', function () {
    $user = User::factory()->create(['role' => 'buyer_admin']);

    actingAs($user);

    $response = $this->postJson('/api/admin/digital-twins', [
        'title' => 'Test',
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('status', 'error');
});

it('publishes a digital twin and updates status', function () {
    $admin = User::factory()->create(['role' => 'platform_super']);
    $twin = DigitalTwin::factory()->create([
        'status' => DigitalTwinStatus::Draft,
    ]);

    actingAs($admin);

    $response = $this->postJson("/api/admin/digital-twins/{$twin->id}/publish");

    $response->assertOk()
        ->assertJsonPath('data.digital_twin.status', DigitalTwinStatus::Published->value);

    expect($twin->refresh()->status)->toBe(DigitalTwinStatus::Published);
});

it('filters digital twins by status', function () {
    $admin = User::factory()->create(['role' => 'platform_super']);

    DigitalTwin::factory()->create(['title' => 'Draft Twin', 'status' => DigitalTwinStatus::Draft]);
    DigitalTwin::factory()->create(['title' => 'Published Twin', 'status' => DigitalTwinStatus::Published]);

    actingAs($admin);

    $response = $this->getJson('/api/admin/digital-twins?status='.DigitalTwinStatus::Published->value);

    $response->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.title', 'Published Twin');
});

it('allows super admins to upload and delete digital twin assets', function () {
    Storage::fake('s3');

    $admin = User::factory()->create(['role' => 'platform_super']);
    $twin = DigitalTwin::factory()->create();

    actingAs($admin);

    $file = UploadedFile::fake()->create('drawing.step', 1024, 'application/octet-stream');

    $uploadResponse = $this->post('/api/admin/digital-twins/'.$twin->id.'/assets', [
        'file' => $file,
        'type' => 'STEP',
        'is_primary' => true,
    ]);

    $uploadResponse->assertCreated()
        ->assertJsonPath('data.asset.filename', 'drawing.step');

    $assetId = $uploadResponse->json('data.asset.id');

    $this->assertDatabaseHas('digital_twin_assets', [
        'id' => $assetId,
        'digital_twin_id' => $twin->id,
    ]);

    $deleteResponse = $this->delete("/api/admin/digital-twins/{$twin->id}/assets/{$assetId}");

    $deleteResponse->assertOk()
        ->assertJsonPath('status', 'success');

    $this->assertSoftDeleted('digital_twin_assets', ['id' => $assetId]);
});
