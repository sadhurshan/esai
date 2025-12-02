<?php

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\RFQ;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createCompanyReadyForRfqLifecycle(array $overrides = []): Company
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

function attachCompanyUser(Company $company, User $user): void
{
    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => $user->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('rejects cancelling closed rfqs', function (): void {
    $company = createCompanyReadyForRfqLifecycle();
    $owner = User::factory()->owner()->create([
        'company_id' => $company->id,
    ]);
    attachCompanyUser($company, $owner);

    $rfq = RFQ::factory()->for($company)->create([
        'status' => RFQ::STATUS_CLOSED,
        'created_by' => $owner->id,
    ]);

    actingAs($owner);

    $response = $this->postJson("/api/rfqs/{$rfq->id}/cancel", [
        'reason' => 'Buyer already placed order elsewhere.',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.status.0', 'Closed RFQs cannot be cancelled.');
});

it('cancels a draft rfq with metadata when allowed', function (): void {
    $company = createCompanyReadyForRfqLifecycle();
    $owner = User::factory()->owner()->create([
        'company_id' => $company->id,
    ]);
    attachCompanyUser($company, $owner);

    $rfq = RFQ::factory()->for($company)->create([
        'status' => RFQ::STATUS_DRAFT,
        'created_by' => $owner->id,
    ]);

    actingAs($owner);

    $cancelledAt = Carbon::now()->addDay();

    $response = $this->postJson("/api/rfqs/{$rfq->id}/cancel", [
        'reason' => 'Revising requirements',
        'cancelled_at' => $cancelledAt->toIso8601String(),
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', RFQ::STATUS_CANCELLED)
        ->assertJsonPath('data.meta.cancellation_reason', 'Revising requirements');
});
