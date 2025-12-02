<?php

use App\Models\Company;
use App\Models\RFQ;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\RfqPayloadFactory;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function attachMembership(User $user, Company $company, string $role = 'buyer_admin'): void
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

function companyWithCommunityPlan(array $overrides = []): Company
{
    return createSubscribedCompany($overrides);
}

/**
 * @return array<string, mixed>
 */
function authorizationRfqOverrides(): array
{
    return [
        'title' => 'Precision Housing',
        'material' => '6061 Aluminum',
        'delivery_location' => 'Wayfinder Labs',
        'due_at' => now()->addDays(10)->toIso8601String(),
        'open_bidding' => false,
        'notes' => 'Prototype batch',
        'items' => [[
            'description' => 'Per attached print',
            'qty' => 25,
            'target_price' => 12.5,
            'material' => '6061 Aluminum',
            'tolerance' => '+/-0.005"',
            'finish' => 'Anodized',
        ]],
    ];
}

it('prevents viewing rfqs outside the active company', function (): void {
    $companyA = companyWithCommunityPlan();
    $companyB = companyWithCommunityPlan();

    $owner = User::factory()->for($companyA)->create(['role' => 'buyer_admin']);
    attachMembership($owner, $companyA);

    $rfq = RFQ::factory()->create([
        'company_id' => $companyA->id,
        'created_by' => $owner->id,
        'status' => RFQ::STATUS_DRAFT,
    ]);

    $intruder = User::factory()->for($companyB)->create(['role' => 'buyer_admin']);
    attachMembership($intruder, $companyB);

    $this->actingAs($intruder)
        ->getJson("/api/rfqs/{$rfq->id}")
        ->assertStatus(404);

    $this->actingAs($owner)
        ->getJson("/api/rfqs/{$rfq->id}")
        ->assertOk()
        ->assertJsonPath('data.id', (string) $rfq->id);
});

it('prevents updating rfqs owned by another company', function (): void {
    $companyA = companyWithCommunityPlan();
    $companyB = companyWithCommunityPlan();

    $owner = User::factory()->for($companyA)->create(['role' => 'buyer_admin']);
    attachMembership($owner, $companyA);

    $rfq = RFQ::factory()->create([
        'company_id' => $companyA->id,
        'created_by' => $owner->id,
        'status' => RFQ::STATUS_DRAFT,
    ]);

    $intruder = User::factory()->for($companyB)->create(['role' => 'buyer_admin']);
    attachMembership($intruder, $companyB);

    $this->actingAs($intruder)
        ->putJson("/api/rfqs/{$rfq->id}", [
            'title' => 'Intrusion',
        ])
        ->assertStatus(404);

    $this->actingAs($owner)
        ->putJson("/api/rfqs/{$rfq->id}", [
            'title' => 'Updated Item',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated Item');
});

it('prevents deleting rfqs across tenants', function (): void {
    $companyA = companyWithCommunityPlan();
    $companyB = companyWithCommunityPlan();

    $owner = User::factory()->for($companyA)->create(['role' => 'buyer_admin']);
    attachMembership($owner, $companyA);

    $rfq = RFQ::factory()->create([
        'company_id' => $companyA->id,
        'created_by' => $owner->id,
    ]);

    $intruder = User::factory()->for($companyB)->create(['role' => 'buyer_admin']);
    attachMembership($intruder, $companyB);

    $this->actingAs($intruder)
        ->deleteJson("/api/rfqs/{$rfq->id}")
        ->assertStatus(404);

    $this->assertDatabaseHas('rfqs', ['id' => $rfq->id]);

    $this->actingAs($owner)
        ->deleteJson("/api/rfqs/{$rfq->id}")
        ->assertOk();

    $this->assertSoftDeleted('rfqs', ['id' => $rfq->id]);
});

it('allows buyer admins to create rfqs when sourcing write access is granted', function (): void {
    $company = companyWithCommunityPlan();
    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    attachMembership($user, $company, 'buyer_admin');

    $this->actingAs($user)
        ->postJson('/api/rfqs', RfqPayloadFactory::make(authorizationRfqOverrides()))
        ->assertStatus(201)
        ->assertJsonPath('data.title', 'Precision Housing')
        ->assertJsonPath('message', 'RFQ created');

    $this->assertDatabaseHas('rfqs', [
        'company_id' => $company->id,
        'title' => 'Precision Housing',
    ]);
});

it('denies rfq creation for roles without rfqs.write permissions', function (string $role): void {
    $company = companyWithCommunityPlan();
    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => $role,
    ]);

    attachMembership($user, $company, $role);

    $this->actingAs($user)
        ->postJson('/api/rfqs', RfqPayloadFactory::make(authorizationRfqOverrides()))
        ->assertStatus(403)
        ->assertJsonPath('message', 'Sourcing write access required.');
})->with([
    'buyer_member' => ['buyer_member'],
    'supplier_admin' => ['supplier_admin'],
    'supplier_estimator' => ['supplier_estimator'],
    'finance' => ['finance'],
]);

it('allows invited suppliers to list rfqs they were invited to', function (): void {
    $buyerCompany = Company::factory()->create();
    $supplierCompany = Company::factory()->create();

    $rfq = RFQ::factory()
        ->for($buyerCompany, 'company')
        ->create([
            'status' => RFQ::STATUS_OPEN,
            'open_bidding' => false,
        ]);

    $supplier = Supplier::factory()->create([
        'company_id' => $supplierCompany->id,
        'status' => 'approved',
    ]);

    RfqInvitation::query()->create([
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'invited_by' => null,
        'status' => RfqInvitation::STATUS_PENDING,
    ]);

    $supplierUser = User::factory()->create([
        'company_id' => $supplierCompany->id,
        'role' => 'supplier_admin',
    ]);

    actingAs($supplierUser)
        ->getJson('/api/rfqs')
        ->assertOk()
        ->assertJsonPath('data.items.0.id', (string) $rfq->id);
});

it('allows suppliers to view open bidding rfqs without invitations', function (): void {
    $buyerCompany = Company::factory()->create();
    $supplierCompany = Company::factory()->create();

    $rfq = RFQ::factory()
        ->for($buyerCompany, 'company')
        ->create([
            'status' => RFQ::STATUS_OPEN,
            'open_bidding' => true,
        ]);

    $supplierUser = User::factory()->create([
        'company_id' => $supplierCompany->id,
        'role' => 'supplier_estimator',
    ]);

    actingAs($supplierUser)
        ->getJson("/api/rfqs/{$rfq->id}")
        ->assertOk()
        ->assertJsonPath('data.id', (string) $rfq->id);
});

it('denies suppliers from viewing private rfqs without invitations', function (): void {
    $buyerCompany = Company::factory()->create();
    $supplierCompany = Company::factory()->create();

    $rfq = RFQ::factory()
        ->for($buyerCompany, 'company')
        ->create([
            'status' => RFQ::STATUS_OPEN,
            'open_bidding' => false,
        ]);

    $supplierUser = User::factory()->create([
        'company_id' => $supplierCompany->id,
        'role' => 'supplier_admin',
    ]);

    actingAs($supplierUser)
        ->getJson("/api/rfqs/{$rfq->id}")
        ->assertNotFound();
});
