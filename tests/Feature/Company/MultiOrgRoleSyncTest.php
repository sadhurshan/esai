<?php

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

it('updates the user role when switching companies', function (): void {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $user = User::factory()->create([
        'company_id' => $companyA->id,
        'role' => 'buyer_admin',
    ]);

    DB::table('company_user')->insert([
        'company_id' => $companyA->id,
        'user_id' => $user->id,
        'role' => 'buyer_admin',
        'is_default' => true,
        'last_used_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('company_user')->insert([
        'company_id' => $companyB->id,
        'user_id' => $user->id,
        'role' => 'buyer_requester',
        'is_default' => false,
        'last_used_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($user);

    postJson('/api/me/companies/switch', [
        'company_id' => $companyB->id,
    ])->assertOk();

    $user->refresh();

    expect($user->company_id)->toBe($companyB->id);
    expect($user->role)->toBe('buyer_requester');
});

it('applies the invitation role when a user accepts and becomes default for that company', function (): void {
    $company = Company::factory()->create();
    $inviter = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    $invitee = User::factory()->create([
        'company_id' => null,
        'role' => 'buyer_member',
    ]);

    $token = 'invitation-token-123';

    CompanyInvitation::query()->create([
        'company_id' => $company->id,
        'invited_by_user_id' => $inviter->id,
        'email' => strtolower($invitee->email),
        'role' => 'buyer_admin',
        'token' => $token,
        'expires_at' => now()->addDay(),
    ]);

    actingAs($invitee);

    postJson("/api/company-invitations/{$token}/accept", [
        'email' => $invitee->email,
    ])->assertOk();

    $invitee->refresh();

    expect($invitee->company_id)->toBe($company->id);
    expect($invitee->role)->toBe('buyer_admin');

    $membership = DB::table('company_user')
        ->where('company_id', $company->id)
        ->where('user_id', $invitee->id)
        ->first();

    expect($membership)->not->toBeNull();
    expect($membership->role)->toBe('buyer_admin');
    expect((bool) $membership->is_default)->toBeTrue();
});
