<?php

use App\Models\Company;
use App\Models\User;

test('rfq compare page requires authentication', function () {
    $this->get(route('rfq.compare', ['id' => 1]))
        ->assertRedirect(route('login'));
});

test('rfq compare page renders for authenticated users', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    $this->actingAs($user);

    $this->get(route('rfq.compare', ['id' => 1]))->assertOk();
});
