<?php

use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;

it('updates the active persona in the session', function (): void {
    $buyerCompany = Company::factory()->create();
    $user = User::factory()->owner()->create([
        'company_id' => $buyerCompany->id,
        'supplier_capable' => true,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $buyerCompany->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplier = CompanyContext::forCompany($buyerCompany->id, fn (): Supplier => Supplier::factory()->create([
        'company_id' => $buyerCompany->id,
    ]));

    CompanyContext::forCompany($buyerCompany->id, function () use ($buyerCompany, $supplier, $user): void {
        SupplierContact::factory()->create([
            'company_id' => $buyerCompany->id,
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
        ]);
    });

    $key = sprintf('supplier:%d:%d', $buyerCompany->id, $supplier->id);

    $response = $this->actingAs($user)
        ->postJson('/api/auth/persona', ['key' => $key])
        ->assertOk()
        ->assertJsonPath('data.active_persona.key', $key);

    expect(session()->get('active_persona'))
        ->toBeArray()
        ->and(session()->get('active_persona')['key'] ?? null)
        ->toBe($key);

    expect($response->json('data.personas'))
        ->toBeArray();
});
