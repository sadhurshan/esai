<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('requires authentication to view profile', function (): void {
    $this->getJson('/api/me/profile')
        ->assertStatus(401)
        ->assertJson(['status' => 'error']);
});

it('shows the authenticated user profile', function (): void {
    $user = User::factory()->create([
        'job_title' => 'Procurement Lead',
        'phone' => '+1-555-0100',
        'locale' => 'en',
        'timezone' => 'America/New_York',
        'avatar_path' => 'avatars/demo.png',
    ]);

    $this->actingAs($user)
        ->getJson('/api/me/profile')
        ->assertOk()
        ->assertJsonPath('data.name', $user->name)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.job_title', 'Procurement Lead')
        ->assertJsonPath('data.phone', '+1-555-0100')
        ->assertJsonPath('data.locale', 'en')
        ->assertJsonPath('data.timezone', 'America/New_York')
        ->assertJsonPath('data.avatar_url', Storage::disk('public')->url('avatars/demo.png'))
        ->assertJsonPath('data.avatar_path', 'avatars/demo.png');
});

it('updates the authenticated user profile', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $payload = [
        'name' => 'Updated User',
        'email' => 'updated@example.com',
        'job_title' => 'Director of Purchasing',
        'phone' => '+1-222-333-4444',
        'locale' => 'fr',
        'timezone' => 'Europe/Paris',
        'avatar_path' => 'avatars/updated.png',
    ];

    $this->actingAs($user)
        ->patchJson('/api/me/profile', $payload)
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated User')
        ->assertJsonPath('data.email', 'updated@example.com')
        ->assertJsonPath('data.job_title', 'Director of Purchasing')
        ->assertJsonPath('data.avatar_url', Storage::disk('public')->url('avatars/updated.png'))
        ->assertJsonPath('data.avatar_path', 'avatars/updated.png');

    $user->refresh();

    expect($user->only(array_keys($payload)))->toMatchArray($payload);
    expect($user->email_verified_at)->toBeNull();
});
