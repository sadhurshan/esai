<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('indicates when email verification is still required', function (): void {
    $user = User::factory()->unverified()->create([
        'email' => 'owner@example.com',
        'password' => bcrypt('Passw0rd!'),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'owner@example.com',
        'password' => 'Passw0rd!',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.requires_email_verification', true)
        ->assertJsonPath('data.user.email_verified_at', null)
        ->assertJsonPath('data.user.has_verified_email', false);

    $this->assertAuthenticatedAs($user);
});

it('clears the verification requirement once confirmed', function (): void {
    $user = User::factory()->create([
        'email' => 'verified@example.com',
        'password' => bcrypt('Passw0rd!'),
        'email_verified_at' => now(),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'verified@example.com',
        'password' => 'Passw0rd!',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.requires_email_verification', false)
        ->assertJsonPath('data.user.email_verified_at', fn ($value) => ! empty($value))
        ->assertJsonPath('data.user.has_verified_email', true);

    $this->assertAuthenticatedAs($user);
});

it('returns personas for supplier-enabled owners', function (): void {
    $buyerCompany = Company::factory()->create();
    $supplierCompany = Company::factory()->create(['supplier_status' => 'approved']);

    $user = User::factory()->create([
        'email' => 'persona@example.com',
        'password' => bcrypt('Passw0rd!'),
        'company_id' => $buyerCompany->id,
    ]);

    $user->companies()->attach($buyerCompany->id, [
        'role' => 'owner',
        'is_default' => true,
    ]);

    $supplier = CompanyContext::forCompany($supplierCompany->id, fn () => Supplier::factory()->create([
        'status' => 'approved',
    ]));

    CompanyContext::forCompany($buyerCompany->id, function () use ($buyerCompany, $supplier, $user): void {
        SupplierContact::factory()->create([
            'company_id' => $buyerCompany->id,
            'supplier_id' => $supplier->id,
            'user_id' => $user->id,
        ]);
    });

    $response = $this->postJson('/api/auth/login', [
        'email' => 'persona@example.com',
        'password' => 'Passw0rd!',
    ]);

    $response->assertOk();

    $response->assertJsonPath('data.personas', function ($personas) use ($buyerCompany, $supplier): bool {
        $collection = collect($personas);

        $buyerMatch = $collection->contains(fn ($persona) => $persona['type'] === 'buyer'
            && (int) ($persona['company_id'] ?? 0) === $buyerCompany->id);

        $supplierMatch = $collection->contains(fn ($persona) => $persona['type'] === 'supplier'
            && (int) ($persona['supplier_id'] ?? 0) === $supplier->id);

        return $buyerMatch && $supplierMatch;
    });

    $response->assertJsonPath('data.active_persona.type', 'buyer');
});
