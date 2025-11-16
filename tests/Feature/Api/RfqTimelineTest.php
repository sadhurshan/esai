<?php

use App\Models\Company;
use App\Models\RFQ;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('returns timeline entries for the owning company', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->owner()->create([
        'company_id' => $company->id,
    ]);

    $rfq = RFQ::factory()->for($company)->create([
        'created_by' => $user->id,
        'sent_at' => Carbon::now()->subDay(),
        'status' => 'open',
    ]);

    actingAs($user);

    $response = $this->getJson("/api/rfqs/{$rfq->id}/timeline");

    $response->assertOk()
        ->assertJsonPath('status', 'success');

    $events = collect($response->json('data.items'))->pluck('event');

    expect($events)->toContain('created');
});

it('forbids timeline access for other companies', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    $user = User::factory()->owner()->create([
        'company_id' => $company->id,
    ]);

    $rfq = RFQ::factory()->for($otherCompany)->create();

    actingAs($user);

    $response = $this->getJson("/api/rfqs/{$rfq->id}/timeline");

    $response->assertForbidden();
});
