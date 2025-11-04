<?php

use App\Models\Company;
use App\Models\User;

test('purchase order index requires authentication', function () {
    $this->get(route('purchase-orders.index'))
        ->assertRedirect(route('login'));
});

test('purchase order index renders for authenticated users', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    $this->actingAs($user);

    $this->get(route('purchase-orders.index'))->assertOk();
});

test('purchase order detail requires authentication', function () {
    $this->get(route('purchase-orders.show', ['id' => 1]))
        ->assertRedirect(route('login'));
});

test('purchase order detail renders for authenticated users', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    $this->actingAs($user);

    $this->get(route('purchase-orders.show', ['id' => 1]))->assertOk();
});
