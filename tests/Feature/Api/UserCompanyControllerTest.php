<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('requires authentication to list companies', function (): void {
    $this->getJson('/api/me/companies')
        ->assertStatus(401)
        ->assertJson(['status' => 'error']);
});

it('lists the companies the user belongs to', function (): void {
    $user = User::factory()->create();
    $primary = Company::factory()->create(['name' => 'Acme Manufacturing']);
    $secondary = Company::factory()->create(['name' => 'Globex Labs']);

    DB::table('company_user')->insert([
        [
            'company_id' => $primary->id,
            'user_id' => $user->id,
            'role' => 'buyer_admin',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'company_id' => $secondary->id,
            'user_id' => $user->id,
            'role' => 'buyer_requester',
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $user->forceFill(['company_id' => $primary->id])->save();

    $this->actingAs($user)
        ->getJson('/api/me/companies')
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.name', 'Acme Manufacturing')
        ->assertJsonPath('data.items.0.is_default', true)
        ->assertJsonPath('data.items.0.is_active', true)
        ->assertJsonPath('data.items.1.name', 'Globex Labs')
        ->assertJsonPath('data.items.1.is_default', false);
});

it('switches the active company when the user belongs to the target tenant', function (): void {
    $user = User::factory()->create();
    $primary = Company::factory()->create();
    $secondary = Company::factory()->create();

    DB::table('company_user')->insert([
        [
            'company_id' => $primary->id,
            'user_id' => $user->id,
            'role' => 'buyer_admin',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'company_id' => $secondary->id,
            'user_id' => $user->id,
            'role' => 'buyer_requester',
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $user->forceFill(['company_id' => $primary->id])->save();

    $this->actingAs($user)
        ->postJson('/api/me/companies/switch', ['company_id' => $secondary->id])
        ->assertOk()
        ->assertJsonPath('data.company_id', $secondary->id);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'company_id' => $secondary->id,
    ]);

    $this->assertDatabaseHas('company_user', [
        'user_id' => $user->id,
        'company_id' => $primary->id,
        'is_default' => false,
    ]);

    $this->assertDatabaseHas('company_user', [
        'user_id' => $user->id,
        'company_id' => $secondary->id,
        'is_default' => true,
    ]);
});

it('rejects switching to a company the user does not belong to', function (): void {
    $user = User::factory()->create();
    $primary = Company::factory()->create();
    $foreign = Company::factory()->create();

    DB::table('company_user')->insert([
        'company_id' => $primary->id,
        'user_id' => $user->id,
        'role' => 'buyer_admin',
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user->forceFill(['company_id' => $primary->id])->save();

    $this->actingAs($user)
        ->postJson('/api/me/companies/switch', ['company_id' => $foreign->id])
        ->assertStatus(403)
        ->assertJson(['status' => 'error']);
});
