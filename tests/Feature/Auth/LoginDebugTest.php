<?php

use App\Models\User;

it('authenticates a user with valid credentials', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'email' => 'login-test@example.com',
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user, 'web');
});
