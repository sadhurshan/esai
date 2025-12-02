<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\RFQ;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;
use function Pest\Laravel\assertSoftDeleted;

test('buyer admin can create an RFQ', function (): void {
    $company = createCompanyWithPlan();
    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    actingAs($user);

    postJson('/api/rfqs', rfqAuthorizationPayload())
        ->assertCreated()
        ->assertJsonPath('status', 'success');
});

test('buyer member cannot create an RFQ', function (): void {
    $company = createCompanyWithPlan();
    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_member',
    ]);

    actingAs($user);

    postJson('/api/rfqs', rfqAuthorizationPayload())
        ->assertStatus(403)
        ->assertJsonPath('status', 'error');
});

test('buyer admin can update an RFQ', function (): void {
    $company = createCompanyWithPlan();
    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    $rfq = RFQ::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'title' => 'Original Part',
        'status' => RFQ::STATUS_DRAFT,
    ]);

    actingAs($user);

    putJson("/api/rfqs/{$rfq->getKey()}", [
        'title' => 'Updated Part',
    ])->assertOk()
        ->assertJsonPath('data.title', 'Updated Part');

    $rfq->refresh();
    expect($rfq->title)->toBe('Updated Part');
});

test('buyer member cannot update an RFQ', function (): void {
    $company = createCompanyWithPlan();
    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_member',
    ]);

    $rfq = RFQ::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'title' => 'Original Part',
        'status' => RFQ::STATUS_DRAFT,
    ]);

    actingAs($user);

    putJson("/api/rfqs/{$rfq->getKey()}", [
        'title' => 'Updated Part',
    ])->assertStatus(403)
        ->assertJsonPath('status', 'error');

    expect($rfq->fresh()->title)->toBe('Original Part');
});

test('buyer admin can delete an RFQ', function (): void {
    $company = createCompanyWithPlan();
    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    $rfq = RFQ::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
    ]);

    actingAs($user);

    deleteJson("/api/rfqs/{$rfq->getKey()}")
           ->assertOk()
        ->assertJsonPath('status', 'success');

    assertSoftDeleted('rfqs', ['id' => $rfq->getKey()]);
});

test('buyer member cannot delete an RFQ', function (): void {
    $company = createCompanyWithPlan();
    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_member',
    ]);

    $rfq = RFQ::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
    ]);

    actingAs($user);

    deleteJson("/api/rfqs/{$rfq->getKey()}")
           ->assertStatus(403)
        ->assertJsonPath('status', 'error');

    expect($rfq->fresh())->not->toBeNull();
});

/**
 * @return array<string, mixed>
 */
function rfqAuthorizationPayload(): array
{
    return [
        'title' => 'CNC Housing',
        'method' => 'cnc',
        'material' => 'aluminum',
        'delivery_location' => 'Acme Manufacturing',
        'due_at' => now()->addDays(5)->toIso8601String(),
        'items' => [
            [
                'part_number' => 'Top Plate',
                'description' => '6061-T6',
                'qty' => 10,
                'uom' => 'pcs',
                'target_price' => 125.50,
                'method' => 'cnc',
                'material' => 'aluminum',
                'tolerance' => '0.01mm',
                'finish' => 'anodized',
            ],
        ],
    ];
}
