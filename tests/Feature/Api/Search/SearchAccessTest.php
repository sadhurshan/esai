<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Services\GlobalSearchService;
use Illuminate\Support\Facades\App;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

class FakeGlobalSearchService extends GlobalSearchService
{
    public function search(array $entityTypes, string $query, array $filters, Company $company, int $page = 1, int $perPage = 20): array
    {
        return [
            'items' => [],
            'meta' => [
                'total' => 0,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => 1,
            ],
        ];
    }
}

beforeEach(function (): void {
    App::instance(GlobalSearchService::class, new FakeGlobalSearchService());
});

function provisionSearchUser(string $role): array
{
    $plan = Plan::factory()->create([
        'code' => 'enterprise-search',
        'price_usd' => 0,
        'global_search_enabled' => true,
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => $role,
    ]);

    return [$company, $user];
}

test('users with read permissions can access global search', function (): void {
    [, $user] = provisionSearchUser('buyer_member');

    actingAs($user);

    getJson('/api/search?q=gasket')
        ->assertOk()
        ->assertJsonPath('status', 'success');
});

test('users without mapped permissions are denied search access', function (): void {
    [, $user] = provisionSearchUser('supplier_estimator');

    actingAs($user);

    getJson('/api/search?q=gasket')
        ->assertForbidden()
        ->assertJsonPath('message', 'Search access requires read permissions.');
});
