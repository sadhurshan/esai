<?php

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Events\CompanyPendingVerification;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertStatus(200);
});

test('new users can register', function () {
    Event::fake([CompanyPendingVerification::class]);

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'test@example.com')->firstOrFail();
    $company = Company::where('owner_user_id', $user->id)->firstOrFail();

    expect($user->role)->toBe('owner')
        ->and($company->status)->toBe(CompanyStatus::PendingVerification)
        ->and($company->supplier_status)->toBe(CompanySupplierStatus::None)
        ->and($company->is_verified)->toBeFalse();

    Event::assertDispatched(CompanyPendingVerification::class, fn (CompanyPendingVerification $event): bool => $event->company->is($company));

    expect(
        AuditLog::query()
            ->where('entity_type', $company->getMorphClass())
            ->where('entity_id', $company->id)
            ->where('action', 'created')
            ->exists()
    )->toBeTrue();
});
