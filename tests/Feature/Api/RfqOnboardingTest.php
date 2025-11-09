<?php

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\RFQ;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function validRfqPayload(): array
{
    return [
        'item_name' => 'Precision Bracket',
        'type' => 'manufacture',
        'quantity' => 100,
        'material' => 'Aluminium 6061',
        'method' => 'CNC Milling',
        'client_company' => 'Elements Supply AI',
        'status' => 'awaiting',
        'notes' => 'Urgent run for pilot build.',
        'items' => [
            [
                'part_name' => 'Bracket A',
                'quantity' => 100,
                'uom' => 'pcs',
            ],
        ],
    ];
}

it('rejects RFQ creation when company onboarding is incomplete', function (): void {
    $company = Company::factory()->create([
        'status' => CompanyStatus::PendingVerification,
        'registration_no' => null,
        'tax_id' => null,
        'country' => null,
        'email_domain' => null,
        'primary_contact_name' => null,
        'primary_contact_email' => null,
        'primary_contact_phone' => null,
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    actingAs($user);

    $response = $this->postJson('/api/rfqs', validRfqPayload());

    $response->assertStatus(403)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('errors.company.0', 'Company onboarding incomplete.');

    expect(RFQ::count())->toBe(0);
});

it('allows RFQ creation after onboarding is complete', function (): void {
    $company = Company::factory()->create([
        'status' => CompanyStatus::PendingVerification,
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    actingAs($user);

    $response = $this->postJson('/api/rfqs', validRfqPayload());

    $response->assertCreated()
        ->assertJsonPath('status', 'success');

    expect(RFQ::count())->toBe(1)
        ->and(RFQ::first()->company_id)->toBe($company->id);
});
