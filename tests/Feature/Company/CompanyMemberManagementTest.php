<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;

it('lists members for the current company', function (): void {
    $company = Company::factory()->create();

    $owner = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'owner',
        'name' => 'Owner User',
    ]);

    $member = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_member',
        'name' => 'Buyer Member',
    ]);

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'is_default' => true,
        'last_used_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $member->id,
        'role' => 'buyer_member',
        'is_default' => false,
        'last_used_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($owner);

    $response = getJson('/api/company-members')
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.meta.per_page', 25)
        ->assertJsonPath('data.meta.next_cursor', null)
        ->assertJsonPath('data.meta.prev_cursor', null)
        ->assertJsonPath('meta.cursor.has_next', false)
        ->assertJsonPath('meta.cursor.has_prev', false);

    $emails = collect($response->json('data.items'))->pluck('email');

    expect($emails)->toContain($owner->email);
    expect($emails)->toContain($member->email);
});

it('detects cross-company role conflicts', function (): void {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $owner = User::factory()->create([
        'company_id' => $companyA->id,
        'role' => 'owner',
    ]);

    $member = User::factory()->create([
        'company_id' => $companyA->id,
        'role' => 'buyer_admin',
    ]);

    foreach ([
        [$owner, $companyA, 'owner', true],
        [$member, $companyA, 'buyer_admin', true],
        [$member, $companyB, 'supplier_admin', false],
    ] as [$user, $company, $role, $isDefault]) {
        DB::table('company_user')->insert([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_default' => $isDefault,
            'last_used_at' => $isDefault ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    actingAs($owner);

    $response = getJson('/api/company-members')->assertOk();

    dump($response->json('data.items'));
    dump(DB::table('company_user')->get()->toArray());

    $memberData = collect($response->json('data.items'))
        ->firstWhere('email', $member->email);

    expect($memberData)->not->toBeNull();
    expect($memberData['role_conflict']['has_conflict'] ?? false)->toBeTrue();
    expect($memberData['role_conflict']['buyer_supplier_conflict'] ?? false)->toBeTrue();
    expect($memberData['role_conflict']['distinct_roles'] ?? [])
        ->toContain('buyer_admin')
        ->toContain('supplier_admin');
});

it('allows owners to update a member role and syncs the active user record', function (): void {
    $company = Company::factory()->create();

    $owner = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'owner',
    ]);

    $member = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_member',
    ]);

    foreach ([[$owner, 'owner', true], [$member, 'buyer_member', false]] as [$user, $role, $isDefault]) {
        DB::table('company_user')->insert([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_default' => $isDefault,
            'last_used_at' => $isDefault ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    actingAs($owner);

    patchJson("/api/company-members/{$member->id}", [
        'role' => 'finance',
    ])->assertOk()->assertJsonPath('data.role', 'finance');

    $member->refresh();

    expect($member->role)->toBe('finance');

    $pivot = DB::table('company_user')
        ->where('company_id', $company->id)
        ->where('user_id', $member->id)
        ->first();

    expect($pivot)->not->toBeNull();
    expect($pivot->role)->toBe('finance');
});

it('prevents removing the final owner in a company', function (): void {
    $company = Company::factory()->create();

    $owner = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'owner',
    ]);

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'is_default' => true,
        'last_used_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($owner);

    deleteJson("/api/company-members/{$owner->id}")
        ->assertStatus(422)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Each company must retain at least one owner.');
});

it('removes a member and reassigns their active membership when another tenant exists', function (): void {
    $companyA = Company::factory()->create(['name' => 'Company A']);
    $companyB = Company::factory()->create(['name' => 'Company B']);

    $owner = User::factory()->create([
        'company_id' => $companyA->id,
        'role' => 'owner',
    ]);

    $member = User::factory()->create([
        'company_id' => $companyA->id,
        'role' => 'buyer_member',
    ]);

    DB::table('company_user')->insert([
        'company_id' => $companyA->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'is_default' => true,
        'last_used_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('company_user')->insert([
        'company_id' => $companyA->id,
        'user_id' => $member->id,
        'role' => 'buyer_member',
        'is_default' => true,
        'last_used_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('company_user')->insert([
        'company_id' => $companyB->id,
        'user_id' => $member->id,
        'role' => 'buyer_requester',
        'is_default' => false,
        'last_used_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($owner);

    deleteJson("/api/company-members/{$member->id}")
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $member->refresh();

    expect($member->company_id)->toBe($companyB->id);
    expect($member->role)->toBe('buyer_requester');

    $remainingMembership = DB::table('company_user')
        ->where('company_id', $companyB->id)
        ->where('user_id', $member->id)
        ->first();

    expect((bool) $remainingMembership->is_default)->toBeTrue();
});
