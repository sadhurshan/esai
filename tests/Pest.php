<?php

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createMoneyFeatureUser(array $planOverrides = [], array $companyOverrides = [], string $role = 'buyer_admin'): User
{
    $plan = Plan::factory()->create(array_merge([
        'code' => 'money-'.Str::lower(Str::random(8)),
        'multi_currency_enabled' => true,
        'tax_engine_enabled' => true,
        'rfqs_per_month' => 50,
        'invoices_per_month' => 50,
        'users_max' => 10,
        'storage_gb' => 10,
    ], $planOverrides));

    $company = Company::factory()->create(array_merge([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'status' => 'active',
    ], $companyOverrides));

    Subscription::factory()->for($company)->create([
        'stripe_status' => 'active',
    ]);

    $user = User::factory()->for($company)->create([
        'role' => $role,
    ]);

    actingAs($user);

    return $user;
}

function createExportFeatureUser(array $planOverrides = [], array $companyOverrides = [], string $role = 'buyer_admin'): User
{
    $plan = Plan::factory()->create(array_merge([
        'code' => 'export-'.Str::lower(Str::random(8)),
        'exports_enabled' => true,
        'export_row_limit' => 50000,
        'export_history_days' => 30,
        'rfqs_per_month' => 50,
        'invoices_per_month' => 50,
        'users_max' => 10,
        'storage_gb' => 10,
    ], $planOverrides));

    $company = Company::factory()->create(array_merge([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'status' => 'active',
    ], $companyOverrides));

    Subscription::factory()->for($company)->create([
        'stripe_status' => 'active',
    ]);

    $user = User::factory()->for($company)->create([
        'role' => $role,
    ]);

    actingAs($user);

    return $user;
}

function createLocalizationFeatureUser(array $planOverrides = [], array $companyOverrides = [], string $role = 'buyer_admin'): User
{
    $plan = Plan::factory()->create(array_merge([
        'code' => 'locale-'.Str::lower(Str::random(8)),
        'localization_enabled' => true,
        'rfqs_per_month' => 50,
        'invoices_per_month' => 50,
        'users_max' => 10,
        'storage_gb' => 10,
    ], $planOverrides));

    $company = Company::factory()->create(array_merge([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'status' => 'active',
    ], $companyOverrides));

    Subscription::factory()->for($company)->create([
        'stripe_status' => 'active',
    ]);

    $user = User::factory()->for($company)->create([
        'role' => $role,
    ]);

    actingAs($user);

    return $user;
}

function createCompanyWithPlan(array $companyOverrides = [], array $planOverrides = []): Company
{
    $planAttributes = array_merge([
        'code' => 'starter',
        'name' => 'Starter',
        'price_usd' => 0,
        'rfqs_per_month' => 50,
        'invoices_per_month' => 50,
        'users_max' => 25,
        'storage_gb' => 20,
    ], $planOverrides);

    $plan = Plan::firstOrCreate(['code' => $planAttributes['code']], $planAttributes);

    $company = Company::factory()->create(array_merge([
        'status' => CompanyStatus::Active->value,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'rfqs_monthly_used' => 0,
    ], $companyOverrides));

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

function createSubscribedCompany(array $companyOverrides = [], array $planOverrides = []): Company
{
    $planAttributes = array_merge([
        'code' => 'community',
        'name' => 'Community',
        'price_usd' => 0,
        'rfqs_per_month' => 25,
        'invoices_per_month' => 25,
        'users_max' => 10,
        'storage_gb' => 5,
    ], $planOverrides);

    $plan = Plan::firstOrCreate(['code' => $planAttributes['code']], $planAttributes);

    $company = Company::factory()->create(array_merge([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::Pending->value,
        'is_verified' => false,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'rfqs_monthly_used' => 0,
        'storage_used_mb' => 0,
    ], $companyOverrides));

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
