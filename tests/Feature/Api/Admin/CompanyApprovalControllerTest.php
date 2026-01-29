<?php

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;
use App\Notifications\CompanyApproved;
use App\Notifications\CompanyRejected;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('notifies owners and platform operators when a company is approved', function (): void {
    Notification::fake();

    $owner = User::factory()->create([
        'role' => 'owner',
    ]);

    $company = Company::factory()->create([
        'status' => CompanyStatus::PendingVerification,
        'owner_user_id' => $owner->id,
    ]);

    $owner->forceFill(['company_id' => $company->id])->save();

    $platformAdmin = User::factory()->create([
        'role' => 'platform_super',
        'company_id' => null,
    ]);

    $platformSupport = User::factory()->create([
        'role' => 'platform_support',
        'company_id' => null,
    ]);

    actingAs($platformAdmin);

    $response = $this->postJson("/api/admin/companies/{$company->id}/approve");

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Company approved.');

    $company->refresh();

    expect($company->status)->toBe(CompanyStatus::Active);

    Notification::assertSentTo(
        $owner,
        CompanyApproved::class,
        function (CompanyApproved $notification) use ($company): bool {
            return $notification->company->is($company)
                && $notification->audience === 'owner';
        }
    );

    Notification::assertSentTo(
        $platformSupport,
        CompanyApproved::class,
        function (CompanyApproved $notification) use ($company): bool {
            return $notification->company->is($company)
                && $notification->audience === 'platform';
        }
    );
});

it('notifies owners and platform operators when a company is rejected', function (): void {
    Notification::fake();

    $owner = User::factory()->create([
        'role' => 'owner',
    ]);

    $company = Company::factory()->create([
        'status' => CompanyStatus::Pending,
        'owner_user_id' => $owner->id,
    ]);

    $owner->forceFill(['company_id' => $company->id])->save();

    $platformAdmin = User::factory()->create([
        'role' => 'platform_super',
        'company_id' => null,
    ]);

    $platformSupport = User::factory()->create([
        'role' => 'platform_support',
        'company_id' => null,
    ]);

    actingAs($platformAdmin);

    $reason = 'Missing insurance certificate.';

    $response = $this->postJson("/api/admin/companies/{$company->id}/reject", [
        'reason' => $reason,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Company rejected.');

    $company->refresh();

    expect($company->status)->toBe(CompanyStatus::Rejected)
        ->and($company->rejection_reason)->toBe($reason);

    Notification::assertSentTo(
        $owner,
        CompanyRejected::class,
        function (CompanyRejected $notification) use ($company, $reason): bool {
            return $notification->company->is($company)
                && $notification->reason === $reason
                && $notification->audience === 'owner';
        }
    );

    Notification::assertSentTo(
        $platformSupport,
        CompanyRejected::class,
        function (CompanyRejected $notification) use ($company, $reason): bool {
            return $notification->company->is($company)
                && $notification->reason === $reason
                && $notification->audience === 'platform';
        }
    );
});

it('fetches companies house profile data for uk companies', function (): void {
    config()->set('services.companies_house.api_key', 'test-key');

    Http::fake([
        'https://api.company-information.service.gov.uk/company/*' => Http::response([
            'company_name' => 'ACME LTD',
            'company_number' => '12345678',
            'company_status' => 'active',
            'registered_office_address' => [
                'address_line_1' => '1 Main Street',
                'postal_code' => 'AB1 2CD',
            ],
        ], 200),
    ]);

    $admin = User::factory()->create([
        'role' => 'platform_super',
        'company_id' => null,
    ]);

    $company = Company::factory()->create([
        'country' => 'United Kingdom',
        'registration_no' => '12345678',
    ]);

    actingAs($admin);

    $response = $this->getJson("/api/admin/company-approvals/{$company->id}/companies-house");

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.profile.company_name', 'ACME LTD')
        ->assertJsonPath('data.profile.company_number', '12345678');
});

it('blocks non platform admins from fetching companies house data', function (): void {
    config()->set('services.companies_house.api_key', 'test-key');

    $user = User::factory()->create([
        'role' => 'owner',
    ]);

    $company = Company::factory()->create([
        'country' => 'United Kingdom',
        'registration_no' => '12345678',
    ]);

    actingAs($user);

    $response = $this->getJson("/api/admin/company-approvals/{$company->id}/companies-house");

    $response->assertForbidden();
});

it('fails when the company is not registered in the united kingdom', function (): void {
    config()->set('services.companies_house.api_key', 'test-key');

    $admin = User::factory()->create([
        'role' => 'platform_super',
        'company_id' => null,
    ]);

    $company = Company::factory()->create([
        'country' => 'Germany',
        'registration_no' => 'DE123456',
    ]);

    actingAs($admin);

    $response = $this->getJson("/api/admin/company-approvals/{$company->id}/companies-house");

    $response
        ->assertStatus(422)
        ->assertJsonPath('status', 'error');
});
