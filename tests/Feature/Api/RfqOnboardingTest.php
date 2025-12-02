<?php

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\RFQ;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RfqPayloadFactory;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

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

    $response = $this->postJson('/api/rfqs', RfqPayloadFactory::make());

    $response->assertStatus(403)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('errors.company.0', 'Company onboarding incomplete.');

    expect(RFQ::count())->toBe(0);
});

it('blocks RFQ creation until the company is approved even when onboarding is complete', function (): void {
    $company = Company::factory()->create([
        'status' => CompanyStatus::PendingVerification,
        'registration_no' => 'REG-100',
        'tax_id' => 'TAX-200',
        'country' => 'US',
        'email_domain' => 'example.com',
        'primary_contact_name' => 'Casey Owner',
        'primary_contact_email' => 'owner@example.com',
        'primary_contact_phone' => '+1-555-0100',
        'address' => '100 Main St',
        'phone' => '+1-555-0100',
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    actingAs($user);

    $response = $this->postJson('/api/rfqs', RfqPayloadFactory::make());

    $response->assertStatus(403)
        ->assertJsonPath('errors.company.0', 'Company approval pending. A platform admin must verify your documents first.');

    expect(RFQ::count())->toBe(0);
});

it('allows RFQ creation after approval is complete', function (): void {
    $plan = Plan::factory()->create([
        'rfqs_per_month' => 10,
    ]);

    $company = Company::factory()->create([
        'status' => CompanyStatus::Active,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'rfqs_monthly_used' => 0,
    ]);

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'stripe_status' => 'active',
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    actingAs($user);

    $response = $this->postJson('/api/rfqs', RfqPayloadFactory::make());

    $response->assertCreated()
        ->assertJsonPath('status', 'success');

    expect(RFQ::count())->toBe(1)
        ->and(RFQ::first()->company_id)->toBe($company->id);
});
