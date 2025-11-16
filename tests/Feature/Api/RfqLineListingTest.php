<?php

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\RFQ;
use App\Models\RfqItem;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createCompanyWithPlan(array $overrides = []): Company
{
    $plan = Plan::firstOrCreate(
        ['code' => 'starter'],
        Plan::factory()->make(['code' => 'starter'])->getAttributes()
    );

    $company = Company::factory()->create(array_merge([
        'status' => CompanyStatus::Active->value,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ], $overrides));

    $customer = Customer::factory()->create([
        'company_id' => $company->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'stripe_status' => 'active',
    ]);

    return $company;
}

it('lists RFQ lines for the owning company', function (): void {
    $company = createCompanyWithPlan();

    $owner = User::factory()->owner()->create([
        'company_id' => $company->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'role' => $owner->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rfq = RFQ::factory()->for($company)->create([
        'created_by' => $owner->id,
    ]);

    RfqItem::factory()->count(2)->create([
        'rfq_id' => $rfq->id,
    ]);

    actingAs($owner);

    $response = $this->getJson("/api/rfqs/{$rfq->id}/lines");

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(2, 'data.items');
});

it('forbids listing RFQ lines for another company', function (): void {
    $company = createCompanyWithPlan();
    $otherCompany = createCompanyWithPlan();

    $owner = User::factory()->owner()->create([
        'company_id' => $company->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'role' => $owner->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rfq = RFQ::factory()->for($otherCompany)->create([
        'created_by' => User::factory()->owner()->create([
            'company_id' => $otherCompany->id,
        ])->id,
    ]);

    actingAs($owner);

    $response = $this->getJson("/api/rfqs/{$rfq->id}/lines");

    $response->assertForbidden();
});
