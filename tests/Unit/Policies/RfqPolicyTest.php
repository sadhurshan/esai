<?php

use App\Models\Company;
use App\Models\RFQ;
use App\Models\User;
use App\Policies\RfqPolicy;
use App\Support\ActivePersona;
use App\Support\ActivePersonaContext;
use Illuminate\Support\Facades\DB;

it('denies rfq creation when acting as supplier persona', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->owner()->create([
        'company_id' => $company->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $persona = ActivePersona::fromArray([
        'key' => sprintf('supplier:%d:999', $company->id),
        'type' => ActivePersona::TYPE_SUPPLIER,
        'company_id' => $company->id,
        'supplier_id' => 999,
    ]);

    expect($persona)->not()->toBeNull();

    ActivePersonaContext::set($persona);

    $policy = app(RfqPolicy::class);

    expect($policy->create($user))->toBeFalse();

    ActivePersonaContext::clear();
});

it('denies rfq viewing for supplier personas outside their invited buyer tenant', function (): void {
    $invitingCompany = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $rfq = RFQ::factory()->create(['company_id' => $otherCompany->id]);

    $user = User::factory()->owner()->create([
        'company_id' => $invitingCompany->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $invitingCompany->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $persona = ActivePersona::fromArray([
        'key' => sprintf('supplier:%d:%d', $invitingCompany->id, 123),
        'type' => ActivePersona::TYPE_SUPPLIER,
        'company_id' => $invitingCompany->id,
        'supplier_id' => 123,
    ]);

    expect($persona)->not()->toBeNull();

    ActivePersonaContext::set($persona);

    $policy = app(RfqPolicy::class);

    expect($policy->view($user, $rfq))->toBeFalse();

    ActivePersonaContext::clear();
});

it('enforces buyer persona tenant boundaries when viewing rfqs', function (): void {
    $primaryCompany = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $rfq = RFQ::factory()->create(['company_id' => $otherCompany->id]);

    $user = User::factory()->owner()->create([
        'company_id' => $primaryCompany->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $primaryCompany->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // User also belongs to the secondary company but is not acting under that persona.
    DB::table('company_user')->insert([
        'company_id' => $otherCompany->id,
        'user_id' => $user->id,
        'role' => 'buyer_admin',
        'is_default' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $persona = ActivePersona::fromArray([
        'key' => sprintf('buyer:%d', $primaryCompany->id),
        'type' => ActivePersona::TYPE_BUYER,
        'company_id' => $primaryCompany->id,
    ]);

    expect($persona)->not()->toBeNull();

    ActivePersonaContext::set($persona);

    $policy = app(RfqPolicy::class);

    expect($policy->view($user, $rfq))->toBeFalse();

    ActivePersonaContext::clear();
});
