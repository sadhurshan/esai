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

function createCompanyReadyForPublishing(array $overrides = []): Company
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

it('publishes an RFQ for the owning company', function (): void {
    $company = createCompanyReadyForPublishing();

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
        'status' => 'awaiting',
    ]);

    actingAs($owner);

    $dueAt = Carbon::now()->addDays(7);
    $publishAt = Carbon::now()->addHour();

    $response = $this->postJson("/api/rfqs/{$rfq->id}/publish", [
        'due_at' => $dueAt->toIso8601String(),
        'publish_at' => $publishAt->toIso8601String(),
        'notify_suppliers' => true,
        'message' => 'Please confirm receipt.',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.deadline_at', $dueAt->toIso8601String());

    $company->refresh();
    expect($company->rfqs_monthly_used)->toBe(1);
});

it('forbids publishing RFQs owned by another company', function (): void {
    $company = createCompanyReadyForPublishing();
    $otherCompany = createCompanyReadyForPublishing();

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
        'status' => 'awaiting',
    ]);

    actingAs($owner);

    $response = $this->postJson("/api/rfqs/{$rfq->id}/publish", [
        'due_at' => Carbon::now()->addDays(5)->toIso8601String(),
    ]);

    $response->assertForbidden();
});
