<?php

use App\Models\PlatformAdmin;
use App\Models\SupplierApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

it('returns supplier applications for platform operators', function (): void {
    $user = User::factory()->create([
        'role' => 'platform_super',
    ]);
    PlatformAdmin::factory()->super()->for($user)->create();

    SupplierApplication::factory()->count(2)->create();

    actingAs($user);

    $response = getJson('/api/admin/supplier-applications');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(2, 'data.items');
});

it('requires admin guard for supplier application review routes', function (): void {
    getJson('/api/admin/supplier-applications')->assertUnauthorized();

    $user = User::factory()->create();
    actingAs($user);

    getJson('/api/admin/supplier-applications')->assertForbidden();
});
