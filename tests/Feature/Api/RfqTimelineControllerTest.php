<?php

use App\Enums\RfqClarificationType;
use App\Models\Company;
use App\Models\Plan;
use App\Models\RFQ;
use App\Models\RfqClarification;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Auth\PersonaResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

function rfqTimelineTestContext(): array
{
    $plan = Plan::factory()->create([
        'code' => 'rfq-timeline-plan',
        'rfqs_per_month' => 25,
        'invoices_per_month' => 25,
        'users_max' => 10,
        'price_usd' => 0,
    ]);

    $buyerCompany = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $supplierCompany = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $buyer = User::factory()->create([
        'company_id' => $buyerCompany->id,
        'role' => 'buyer_admin',
    ]);

    $supplierUser = User::factory()->create([
        'company_id' => $supplierCompany->id,
        'role' => 'supplier_admin',
    ]);

    $supplierProfile = Supplier::factory()->create([
        'company_id' => $supplierCompany->id,
    ]);

    $rfq = RFQ::factory()->create([
        'company_id' => $buyerCompany->id,
        'created_by' => $buyer->id,
        'status' => RFQ::STATUS_OPEN,
        'number' => 'RFQ-9001',
        'title' => 'Timeline Fixture',
        'publish_at' => now()->subDay(),
        'due_at' => now()->addDays(5),
    ]);

    RfqInvitation::create([
        'company_id' => $buyerCompany->id,
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplierProfile->id,
        'invited_by' => $buyer->id,
        'status' => RfqInvitation::STATUS_PENDING,
    ]);

    RfqClarification::factory()->create([
        'company_id' => $buyerCompany->id,
        'rfq_id' => $rfq->id,
        'user_id' => $supplierUser->id,
        'type' => RfqClarificationType::Question,
        'message' => 'Supplier needs tolerances.',
        'created_at' => now()->subHours(2),
    ]);

    RfqClarification::factory()->create([
        'company_id' => $buyerCompany->id,
        'rfq_id' => $rfq->id,
        'user_id' => $buyer->id,
        'type' => RfqClarificationType::Answer,
        'message' => 'Tolerances are +/- 0.002 in.',
        'created_at' => now()->subHour(),
    ]);

    return [
        'buyer' => $buyer,
        'supplier' => $supplierUser,
        'rfq' => $rfq,
    ];
}

it('returns timeline entries for buyer company users', function (): void {
    $context = rfqTimelineTestContext();

    actingAs($context['buyer']);

    $response = getJson("/api/rfqs/{$context['rfq']->id}/timeline");

    $response->assertOk();

    $events = collect(data_get($response->json(), 'data.items', []))->pluck('event');

    expect($events)->toContain('created')
        ->and($events)->toContain('question_posted')
        ->and($events)->toContain('answer_posted');
});

it('lets invited suppliers view clarification events without seeing other supplier invites', function (): void {
    $context = rfqTimelineTestContext();

    actingAs($context['supplier']);

    $response = getJson("/api/rfqs/{$context['rfq']->id}/timeline");

    $response->assertOk();

    $events = collect(data_get($response->json(), 'data.items', []))->pluck('event');

    expect($events)->toContain('question_posted')
        ->and($events)->not->toContain('invitation_sent');
});

it('allows buyers acting through a different persona to view the timeline', function (): void {
    $primaryCompany = Company::factory()->create();
    $secondaryCompany = Company::factory()->create();

    $user = User::factory()->create([
        'company_id' => $primaryCompany->id,
        'role' => 'owner',
    ]);

    $now = now();

    $user->companies()->attach($primaryCompany->id, [
        'role' => 'owner',
        'is_default' => true,
        'last_used_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $user->companies()->attach($secondaryCompany->id, [
        'role' => 'buyer_admin',
        'is_default' => false,
        'last_used_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $rfq = RFQ::factory()->for($secondaryCompany)->create([
        'created_by' => $user->id,
        'status' => RFQ::STATUS_OPEN,
    ]);

    $personas = app(PersonaResolver::class)->resolve($user->fresh());
    $secondaryPersona = collect($personas)->first(static function (array $persona) use ($secondaryCompany): bool {
        return $persona['type'] === 'buyer' && (int) $persona['company_id'] === $secondaryCompany->id;
    });

    expect($secondaryPersona)->not->toBeNull();

    actingAs($user);

    $response = $this->withHeaders([
        'X-Active-Persona' => $secondaryPersona['key'],
    ])->getJson("/api/rfqs/{$rfq->id}/timeline");

    $response->assertOk();
});
