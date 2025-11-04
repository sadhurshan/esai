<?php

use App\Models\Company;
use App\Models\User;

test('users without a company are redirected to the company registration wizard', function () {
    $user = User::factory()->create([
        'company_id' => null,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('company.registration', absolute: false));
});

test('the company registration wizard is accessible to authenticated users without a company', function () {
    $user = User::factory()->create([
        'company_id' => null,
    ]);

    $response = $this->actingAs($user)->get(route('company.registration'));

    $response->assertOk();
});

test('users with a company can access the dashboard', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
});
