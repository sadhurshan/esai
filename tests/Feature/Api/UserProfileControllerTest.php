<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
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
    Storage::fake('public');

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
    ];

    $response = $this
        ->actingAs($user)
        ->patch('/api/me/profile', [
            ...$payload,
            'avatar' => UploadedFile::fake()->image('updated.png', 200, 200),
        ], [
            'Accept' => 'application/json',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated User')
        ->assertJsonPath('data.email', 'updated@example.com')
        ->assertJsonPath('data.job_title', 'Director of Purchasing');

    $updatedPath = $response->json('data.avatar_path');
    $this->assertNotEmpty($updatedPath);

    $response->assertJsonPath('data.avatar_url', Storage::disk('public')->url($updatedPath));

    $user->refresh();

    expect($user->only(array_keys($payload)))->toMatchArray($payload);
    expect($user->avatar_path)->toBe($updatedPath);
    Storage::disk('public')->assertExists($updatedPath);
    expect($user->email_verified_at)->toBeNull();
});

it('allows removing an existing avatar through the API', function (): void {
    Storage::fake('public');

    $user = User::factory()->create([
        'avatar_path' => null,
    ]);

    $existingPath = sprintf('avatars/%d/current.png', $user->id);
    $user->forceFill(['avatar_path' => $existingPath])->save();
    Storage::disk('public')->put($existingPath, 'avatar-bytes');

    $this
        ->actingAs($user)
        ->patch('/api/me/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'avatar_path' => '',
        ], [
            'Accept' => 'application/json',
        ])
        ->assertOk()
        ->assertJsonPath('data.avatar_path', null)
        ->assertJsonPath('data.avatar_url', null);

    $user->refresh();

    expect($user->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($existingPath);
});
