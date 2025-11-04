<?php

use App\Models\Company;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))->assertOk();
});
