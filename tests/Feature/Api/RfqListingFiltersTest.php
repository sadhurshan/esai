<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\RFQ;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('filters rfqs by the documented query parameters', function (): void {
    $now = Carbon::parse('2025-11-22 12:00:00');
    Carbon::setTestNow($now);

    $plan = Plan::factory()->create([
        'code' => 'enterprise',
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    $matching = RFQ::factory()->create([
        'company_id' => $company->id,
        'status' => RFQ::STATUS_OPEN,
        'open_bidding' => true,
        'method' => 'cnc',
        'material' => '6061 Aluminum',
        'title' => 'Pump Housing Rev A',
        'number' => 'RFQ-90001',
        'due_at' => $now->copy()->addDays(7),
        'publish_at' => $now->copy()->subDay(),
    ]);

    RFQ::factory()->create([
        'company_id' => $company->id,
        'status' => RFQ::STATUS_DRAFT,
        'open_bidding' => true,
    ]);

    RFQ::factory()->create([
        'company_id' => $company->id,
        'status' => RFQ::STATUS_OPEN,
        'open_bidding' => false,
        'method' => 'cnc',
        'material' => '6061 Aluminum',
        'due_at' => $now->copy()->addDays(7),
    ]);

    RFQ::factory()->create([
        'company_id' => $company->id,
        'status' => RFQ::STATUS_OPEN,
        'open_bidding' => true,
        'method' => 'sheet_metal',
        'material' => '6061 Aluminum',
        'due_at' => $now->copy()->addDays(7),
    ]);

    RFQ::factory()->create([
        'company_id' => $company->id,
        'status' => RFQ::STATUS_OPEN,
        'open_bidding' => true,
        'method' => 'cnc',
        'material' => 'Stainless Steel',
        'due_at' => $now->copy()->addDays(7),
    ]);

    RFQ::factory()->create([
        'company_id' => $company->id,
        'status' => RFQ::STATUS_OPEN,
        'open_bidding' => true,
        'method' => 'cnc',
        'material' => '6061 Aluminum',
        'due_at' => $now->copy()->addDays(2),
    ]);

    RFQ::factory()->create([
        'status' => RFQ::STATUS_OPEN,
        'open_bidding' => true,
    ]);

    actingAs($user)
        ->getJson('/api/rfqs?'.http_build_query([
            'status' => 'open',
            'open_bidding' => 'true',
            'method' => 'cnc',
            'material' => '6061 Aluminum',
            'due_from' => $now->copy()->addDays(6)->toDateString(),
            'due_to' => $now->copy()->addDays(8)->toDateString(),
            'search' => 'Pump',
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', (string) $matching->id)
        ->assertJsonStructure([
            'data' => [
                'meta' => [
                    'next_cursor',
                    'prev_cursor',
                    'per_page',
                ],
            ],
            'meta' => [
                'cursor' => [
                    'next_cursor',
                    'prev_cursor',
                    'has_next',
                    'has_prev',
                ],
            ],
        ])
        ->assertJsonPath('data.meta.per_page', 25)
        ->assertJsonPath('meta.cursor.has_next', false)
        ->assertJsonPath('meta.cursor.has_prev', false);

    Carbon::setTestNow();
});

it('requires an active company context to list rfqs', function (): void {
    $user = User::factory()->create([
        'company_id' => null,
        'role' => 'buyer_admin',
    ]);

    actingAs($user)
        ->getJson('/api/rfqs')
        ->assertStatus(403)
        ->assertJsonPath('message', 'Company context required.');
});

it('paginates rfqs using cursors', function (): void {
    $plan = Plan::factory()->create();

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    $rfqs = RFQ::factory()->count(3)->sequence(
        ['company_id' => $company->id, 'due_at' => now()->addDays(1)],
        ['company_id' => $company->id, 'due_at' => now()->addDays(2)],
        ['company_id' => $company->id, 'due_at' => now()->addDays(3)]
    )->create();

    $first = actingAs($user)
        ->getJson('/api/rfqs?per_page=2&sort=due_at&sort_direction=asc')
        ->assertOk()
        ->assertJsonPath('data.items.0.id', (string) $rfqs[0]->id)
        ->assertJsonPath('data.items.1.id', (string) $rfqs[1]->id)
        ->assertJsonPath('meta.cursor.has_next', true)
        ->assertJsonPath('meta.cursor.has_prev', false)
        ->json();

    $nextCursor = Arr::get($first, 'meta.cursor.next_cursor');

    expect($nextCursor)->not->toBeNull();

    actingAs($user)
        ->getJson('/api/rfqs?per_page=2&sort=due_at&sort_direction=asc&cursor='.$nextCursor)
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', (string) $rfqs[2]->id)
        ->assertJsonPath('meta.cursor.has_next', false)
        ->assertJsonPath('meta.cursor.has_prev', true);
});
