<?php

use App\Actions\Company\InviteCompanyUsersAction;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Notifications\CompanyInvitationIssued;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

function attachUserToCompany(User $user, Company $company, string $role = 'buyer_admin'): void
{
    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => $role,
        'is_default' => true,
        'last_used_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('creates pending users when inviting a new email', function (): void {
    Notification::fake();

    $company = Company::factory()->create();
    $inviter = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    attachUserToCompany($inviter, $company);

    actingAs($inviter);

    postJson('/api/company-invitations', [
        'invitations' => [[
            'email' => 'pending-user@example.com',
            'role' => 'buyer_member',
        ]],
    ])->assertOk();

    $pendingUser = User::where('email', 'pending-user@example.com')->first();
    expect($pendingUser)->not->toBeNull();
    expect($pendingUser?->status)->toBe(UserStatus::Pending);
    expect($pendingUser?->company_id)->toBe($company->id);

    $invitation = CompanyInvitation::query()->first();
    expect($invitation)->not->toBeNull();
    expect($invitation?->pending_user_id)->toBe($pendingUser?->id);

    Notification::assertSentToTimes($pendingUser, CompanyInvitationIssued::class, 1);
});

it('allows pending invitees to accept without an existing session', function (): void {
    Notification::fake();

    $company = Company::factory()->create();
    $inviter = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    attachUserToCompany($inviter, $company);

    /** @var InviteCompanyUsersAction $inviteAction */
    $inviteAction = app(InviteCompanyUsersAction::class);

    $inviteAction->execute($company, $inviter, [[
        'email' => 'accept-now@example.com',
        'role' => 'buyer_admin',
    ]]);

    $invitation = CompanyInvitation::query()->first();
    $pendingUser = User::where('email', 'accept-now@example.com')->firstOrFail();

    postJson("/api/company-invitations/{$invitation->token}/accept", [
        'email' => 'accept-now@example.com',
        'name' => 'Accept Now',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ])->dump()->assertOk();

    $pendingUser->refresh();

    expect($pendingUser->status)->toBe(UserStatus::Active);
    expect(Hash::check('SecurePass123!', $pendingUser->password))->toBeTrue();

    $membership = DB::table('company_user')
        ->where('company_id', $company->id)
        ->where('user_id', $pendingUser->id)
        ->first();

    expect($membership)->not->toBeNull();
    expect((bool) $membership->is_default)->toBeTrue();
});

it('rejects invitation acceptance when the email does not match', function (): void {
    Notification::fake();

    $company = Company::factory()->create();
    $inviter = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    attachUserToCompany($inviter, $company);

    actingAs($inviter);

    postJson('/api/company-invitations', [
        'invitations' => [[
            'email' => 'secure@example.com',
            'role' => 'buyer_member',
        ]],
    ])->assertOk();

    $invitation = CompanyInvitation::query()->firstOrFail();

    postJson("/api/company-invitations/{$invitation->token}/accept", [
        'email' => 'mismatch@example.com',
        'name' => 'Wrong Person',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ])->assertStatus(422);
});
