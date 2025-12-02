<?php

use App\Enums\CompanySupplierStatus;
use App\Enums\RfpStatus;
use App\Models\Rfp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function rfpTestCompany(?string $role = 'buyer_admin'): array {
    $company = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => $role,
    ]);

    return compact('company', 'user');
}

it('allows buyer admins to create rfps with the required narrative fields', function (): void {
    ['company' => $company, 'user' => $user] = rfpTestCompany();

    $payload = [
        'title' => 'Electrification Retrofit Program',
        'problem_objectives' => 'Reduce downtime on Line 4 while meeting new ESG thresholds.',
        'scope' => 'Line audit, retrofit design, commissioning, and training.',
        'timeline' => 'Kickoff Jan 2026, go-live Sep 2026',
        'evaluation_criteria' => 'Technical fit, experience, ESG alignment, total cost.',
        'proposal_format' => 'PDF narrative + Excel pricing appendix.',
    ];

    $this->actingAs($user);

    $response = $this->postJson('/api/rfps', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.title', $payload['title'])
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.problem_objectives', $payload['problem_objectives']);

    $this->assertDatabaseHas('rfps', [
        'company_id' => $company->id,
        'title' => $payload['title'],
        'problem_objectives' => $payload['problem_objectives'],
    ]);
});

it('blocks supplier roles from creating rfps', function (): void {
    ['user' => $user] = rfpTestCompany('supplier_admin');

    $this->actingAs($user);

    $response = $this->postJson('/api/rfps', [
        'title' => 'Supplier-Led Initiative',
        'problem_objectives' => 'Test',
        'scope' => 'Test',
        'timeline' => 'Test',
        'evaluation_criteria' => 'Test',
        'proposal_format' => 'Test',
    ]);

    $response->assertForbidden()
        ->assertJsonPath('errors.code', 'rfps_write_required');

    expect(Rfp::query()->count())->toBe(0);
});

it('scopes the rfp listing to the active company context', function (): void {
    ['company' => $company, 'user' => $user] = rfpTestCompany();
    $otherCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    Rfp::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'title' => 'Company Visible RFP',
    ]);

    Rfp::factory()->create([
        'company_id' => $otherCompany->id,
        'created_by' => User::factory()->create(['company_id' => $otherCompany->id])->id,
        'title' => 'Hidden RFP',
    ]);

    $this->actingAs($user);

    $response = $this->getJson('/api/rfps');

    $response->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.title', 'Company Visible RFP');
});

it('enforces the RFP lifecycle transitions with audit logging', function (): void {
    ['company' => $company, 'user' => $user] = rfpTestCompany();

    $rfp = Rfp::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'status' => RfpStatus::Draft->value,
    ]);

    $this->actingAs($user);

    $this->postJson("/api/rfps/{$rfp->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', RfpStatus::Published->value);

    $rfp->refresh();
    expect($rfp->published_at)->not()->toBeNull();

    $this->postJson("/api/rfps/{$rfp->id}/move-to-review")
        ->assertOk()
        ->assertJsonPath('data.status', RfpStatus::InReview->value);

    $rfp->refresh();
    expect($rfp->in_review_at)->not()->toBeNull();

    $this->postJson("/api/rfps/{$rfp->id}/award")
        ->assertOk()
        ->assertJsonPath('data.status', RfpStatus::Awarded->value);

    $rfp->refresh();
    expect($rfp->awarded_at)->not()->toBeNull()
        ->and($rfp->closed_at)->not()->toBeNull();
});

it('rejects invalid RFP transitions and permission violations', function (): void {
    ['company' => $company, 'user' => $user] = rfpTestCompany();
    $supplierUser = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'supplier_admin',
    ]);

    $rfp = Rfp::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'status' => RfpStatus::Draft->value,
    ]);

    $this->actingAs($supplierUser)
        ->postJson("/api/rfps/{$rfp->id}/publish")
        ->assertForbidden()
        ->assertJsonPath('errors.code', 'rfps_write_required');

    $this->actingAs($user);

    // Publish once to exit draft state
    $this->postJson("/api/rfps/{$rfp->id}/publish")->assertOk();

    // Attempt to award without review should fail
    $this->postJson("/api/rfps/{$rfp->id}/award")
        ->assertUnprocessable()
        ->assertJsonPath('errors.code', 'rfp_transition_invalid');
});
