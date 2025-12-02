<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\RFQ;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RfqPayloadFactory;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('rejects the status field when creating an rfq', function (): void {
    $user = createBuyerAdmin();

    actingAs($user)
        ->postJson('/api/rfqs', RfqPayloadFactory::make([
            'status' => 'open',
        ]))
        ->assertStatus(422)
        ->assertJsonPath('errors.status.0', 'The status field is prohibited.');
});

it('rejects the legacy type field when creating an rfq', function (): void {
    $user = createBuyerAdmin();

    actingAs($user)
        ->postJson('/api/rfqs', RfqPayloadFactory::make([
            'type' => 'ready_made',
        ]))
        ->assertStatus(422)
        ->assertJsonPath('errors.type.0', 'The type field is prohibited.');
});

it('rejects status updates through the rfq update endpoint', function (): void {
    $user = createBuyerAdmin();

    $rfq = RFQ::factory()->create([
        'company_id' => $user->company_id,
        'created_by' => $user->id,
        'status' => RFQ::STATUS_DRAFT,
    ]);

    actingAs($user)
        ->putJson("/api/rfqs/{$rfq->getKey()}", [
            'status' => RFQ::STATUS_OPEN,
        ])
        ->assertStatus(422)
        ->assertJsonPath('errors.status.0', 'The status field is prohibited.');
});

it('persists payment terms meta when creating an rfq', function (): void {
    $user = createBuyerAdmin();

    actingAs($user)
        ->postJson('/api/rfqs', RfqPayloadFactory::make([
            'payment_terms' => 'Net 45',
        ]))
        ->assertStatus(201);

    $rfq = RFQ::query()->where('company_id', $user->company_id)->latest()->first();
    expect($rfq)->not->toBeNull();
    expect(data_get($rfq?->meta, 'payment_terms'))->toBe('Net 45');
});

it('updates payment terms meta when editing an rfq', function (): void {
    $user = createBuyerAdmin();

    $rfq = RFQ::factory()->create([
        'company_id' => $user->company_id,
        'created_by' => $user->id,
        'status' => RFQ::STATUS_DRAFT,
        'meta' => ['payment_terms' => 'Net 30'],
    ]);

    actingAs($user)
        ->putJson("/api/rfqs/{$rfq->getKey()}", [
            'payment_terms' => 'Net 60',
        ])
        ->assertOk();

    $rfq->refresh();
    expect(data_get($rfq->meta, 'payment_terms'))->toBe('Net 60');
});

it('rejects direct updates once an rfq is published', function (): void {
    $user = createBuyerAdmin();

    $rfq = RFQ::factory()->create([
        'company_id' => $user->company_id,
        'created_by' => $user->id,
        'status' => RFQ::STATUS_OPEN,
    ]);

    actingAs($user)
        ->putJson("/api/rfqs/{$rfq->getKey()}", [
            'title' => 'Updated title',
        ])
        ->assertStatus(422)
        ->assertJsonPath('errors.status.0', 'RFQs must be amended after publishing.');
});

it('persists tax percent meta when creating an rfq', function (): void {
    $user = createBuyerAdmin();

    actingAs($user)
        ->postJson('/api/rfqs', RfqPayloadFactory::make([
            'tax_percent' => 8.75,
        ]))
        ->assertStatus(201);

    $rfq = RFQ::query()->where('company_id', $user->company_id)->latest()->first();
    expect($rfq)->not->toBeNull();
    expect(data_get($rfq?->meta, 'tax_percent'))->toBe(8.75);
});

it('updates tax percent meta when editing an rfq', function (): void {
    $user = createBuyerAdmin();

    $rfq = RFQ::factory()->create([
        'company_id' => $user->company_id,
        'created_by' => $user->id,
        'status' => RFQ::STATUS_DRAFT,
        'meta' => ['tax_percent' => 5.25],
    ]);

    actingAs($user)
        ->putJson("/api/rfqs/{$rfq->getKey()}", [
            'tax_percent' => 6.5,
        ])
        ->assertOk();

    $rfq->refresh();
    expect(data_get($rfq->meta, 'tax_percent'))->toBe(6.5);
});

it('rejects invalid tax percent values', function (): void {
    $user = createBuyerAdmin();

    actingAs($user)
        ->postJson('/api/rfqs', RfqPayloadFactory::make([
            'tax_percent' => 250,
        ]))
        ->assertStatus(422)
        ->assertJsonPath('errors.tax_percent.0', 'The tax percent field must not be greater than 100.');

    actingAs($user)
        ->postJson('/api/rfqs', RfqPayloadFactory::make([
            'tax_percent' => -3,
        ]))
        ->assertStatus(422)
        ->assertJsonPath('errors.tax_percent.0', 'The tax percent field must be at least 0.');
});

function createBuyerAdmin(): User
{
    $plan = Plan::factory()->create([
        'code' => 'community',
        'price_usd' => 0,
        'rfqs_per_month' => 100,
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    return User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);
}

