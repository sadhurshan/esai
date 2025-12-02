<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('indicates when email verification is still required', function (): void {
    $user = User::factory()->unverified()->create([
        'email' => 'owner@example.com',
        'password' => bcrypt('Passw0rd!'),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'owner@example.com',
        'password' => 'Passw0rd!',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.requires_email_verification', true)
        ->assertJsonPath('data.user.email_verified_at', null)
        ->assertJsonPath('data.user.has_verified_email', false);

    $this->assertAuthenticatedAs($user);
});

it('clears the verification requirement once confirmed', function (): void {
    $user = User::factory()->create([
        'email' => 'verified@example.com',
        'password' => bcrypt('Passw0rd!'),
        'email_verified_at' => now(),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'verified@example.com',
        'password' => 'Passw0rd!',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.requires_email_verification', false)
        ->assertJsonPath('data.user.email_verified_at', fn ($value) => ! empty($value))
        ->assertJsonPath('data.user.has_verified_email', true);

    $this->assertAuthenticatedAs($user);
});
