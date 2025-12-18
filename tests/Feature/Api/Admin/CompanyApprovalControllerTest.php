<?php

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;
use App\Notifications\CompanyApproved;
use App\Notifications\CompanyRejected;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
