<?php

use App\Models\Company;
use App\Models\DigitalTwin;
use App\Models\DigitalTwinCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('allows platform super admins to create digital twin categories', function () {
    $admin = User::factory()->create(['role' => 'platform_super']);

    actingAs($admin);

    $payload = [
        'name' => 'Precision Machining',
        'slug' => 'precision-machining',
        'description' => 'High tolerance machining workflows',
        'is_active' => true,
    ];

    $response = $this->postJson('/api/admin/digital-twin-categories', $payload);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.category.name', 'Precision Machining');

    $this->assertDatabaseHas('digital_twin_categories', [
        'slug' => 'precision-machining',
        'name' => 'Precision Machining',
    ]);
});

it('forbids non-platform users from creating digital twin categories', function () {
    $user = User::factory()->create(['role' => 'buyer_admin']);

    actingAs($user);

    $response = $this->postJson('/api/admin/digital-twin-categories', [
        'name' => 'Test Category',
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('status', 'error');
});

it('returns a nested category tree when requested', function () {
    $admin = User::factory()->create(['role' => 'platform_super']);

    $parent = DigitalTwinCategory::factory()->create(['name' => 'Metal']);
    DigitalTwinCategory::factory()->create([
        'name' => 'Aluminum',
        'parent_id' => $parent->id,
    ]);

    actingAs($admin);

    $response = $this->getJson('/api/admin/digital-twin-categories?tree=1');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.items.0.children.0.parent_id', $parent->id);
});

it('prevents deleting categories that have dependent digital twins', function () {
    $admin = User::factory()->create(['role' => 'platform_super']);
    $category = DigitalTwinCategory::factory()->create();
    $company = Company::factory()->create();

    DigitalTwin::factory()
        ->for($company)
        ->for($category, 'category')
        ->create();

    actingAs($admin);

    $response = $this->deleteJson("/api/admin/digital-twin-categories/{$category->id}");

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error');
});
