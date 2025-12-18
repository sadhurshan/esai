<?php

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * @return array{company: Company, user: User, quote: Quote, plan: Plan}
 */
function buildShortlistTestContext(): array
{
    $plan = Plan::factory()->create([
        'code' => 'enterprise',
        'rfqs_per_month' => 0,
        'invoices_per_month' => 0,
        'users_max' => 0,
        'storage_gb' => 0,
    ]);

    $company = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::None->value,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $customer = Customer::factory()->create(['company_id' => $company->id]);

    Subscription::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'stripe_status' => 'active',
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => $user->role,
        'is_default' => true,
        'last_used_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rfq = RFQ::factory()->create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'status' => 'open',
        'currency' => 'USD',
        'rfq_version' => 1,
        'is_open_bidding' => true,
        'open_bidding' => true,
        'due_at' => now()->addDays(7),
    ]);

    $supplierCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $supplier = Supplier::factory()
        ->for($supplierCompany, 'company')
        ->create([
            'status' => 'approved',
        ]);

    $quote = Quote::factory()->create([
        'company_id' => $company->id,
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'submitted_by' => $user->id,
        'status' => 'submitted',
        'shortlisted_at' => null,
        'shortlisted_by' => null,
    ]);

    return compact('company', 'user', 'quote', 'plan');
}

it('shortlists a quote for the buyers company', function (): void {
    $context = buildShortlistTestContext();

    actingAs($context['user']);

    $response = $this->postJson("/api/quotes/{$context['quote']->id}/shortlist");

    $response
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.quote.is_shortlisted', true)
        ->assertJsonPath('data.quote.shortlisted_by', $context['user']->id);

    $context['quote']->refresh();

    expect($context['quote']->shortlisted_at)->not()->toBeNull()
        ->and($context['quote']->shortlisted_by)->toBe($context['user']->id);
});

it('removes a quote from the shortlist', function (): void {
    $context = buildShortlistTestContext();
    $context['quote']->forceFill([
        'shortlisted_at' => now()->subMinute(),
        'shortlisted_by' => $context['user']->id,
    ])->save();

    actingAs($context['user']);

    $response = $this->deleteJson("/api/quotes/{$context['quote']->id}/shortlist");

    $response
        ->assertOk()
        ->assertJsonPath('data.quote.is_shortlisted', false);

    $context['quote']->refresh();

    expect($context['quote']->shortlisted_at)->toBeNull()
        ->and($context['quote']->shortlisted_by)->toBeNull();
});

it('prevents buyers from other companies from toggling the shortlist', function (): void {
    $context = buildShortlistTestContext();

    $unauthorizedCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'plan_id' => $context['plan']->id,
        'plan_code' => $context['plan']->code,
        'supplier_status' => CompanySupplierStatus::None->value,
    ]);

    $unauthorizedCustomer = Customer::factory()->create([
        'company_id' => $unauthorizedCompany->id,
    ]);

    Subscription::factory()->create([
        'company_id' => $unauthorizedCompany->id,
        'customer_id' => $unauthorizedCustomer->id,
        'stripe_status' => 'active',
    ]);

    $unauthorizedUser = User::factory()->create([
        'company_id' => $unauthorizedCompany->id,
        'role' => 'buyer_admin',
    ]);

    DB::table('company_user')->insert([
        'company_id' => $unauthorizedCompany->id,
        'user_id' => $unauthorizedUser->id,
        'role' => $unauthorizedUser->role,
        'is_default' => true,
        'last_used_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($unauthorizedUser);

    $this->postJson("/api/quotes/{$context['quote']->id}/shortlist")
        ->assertStatus(404);
});
