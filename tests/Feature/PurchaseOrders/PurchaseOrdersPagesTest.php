<?php

use App\Models\User;

test('purchase order index requires authentication', function () {
    $this->get(route('purchase-orders.index'))
        ->assertRedirect(route('login'));
});

test('purchase order index renders for authenticated users', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('purchase-orders.index'))->assertOk();
});

test('purchase order detail requires authentication', function () {
    $this->get(route('purchase-orders.show', ['id' => 1]))
        ->assertRedirect(route('login'));
});

test('purchase order detail renders for authenticated users', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('purchase-orders.show', ['id' => 1]))->assertOk();
});
