<?php

use App\Models\User;

test('rfq compare page requires authentication', function () {
    $this->get(route('rfq.compare', ['id' => 1]))
        ->assertRedirect(route('login'));
});

test('rfq compare page renders for authenticated users', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('rfq.compare', ['id' => 1]))->assertOk();
});
