<?php

use App\Models\Company;
use App\Models\RoleTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

it('allows buyer admins to list role templates', function (): void {
    RoleTemplate::query()->create([
        'slug' => 'owner',
        'name' => 'Owner',
        'description' => 'Full access',
        'permissions' => ['rfqs.read', 'rfqs.write'],
        'is_system' => true,
    ]);

    RoleTemplate::query()->create([
        'slug' => 'buyer_member',
        'name' => 'Buyer member',
        'description' => 'Read-only sourcing',
        'permissions' => ['rfqs.read'],
        'is_system' => true,
    ]);

    $company = Company::factory()->create();

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'buyer_admin',
        'is_default' => true,
        'last_used_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($user);

    getJson('/api/company-role-templates')
        ->assertOk()
        ->assertJsonPath('data.items.0.slug', 'buyer_member')
        ->assertJsonPath('data.items.1.slug', 'owner')
        ->assertJsonPath('data.permission_groups', fn ($groups) => is_array($groups))
        ->assertJsonPath('meta.cursor.has_next', false);
});

it('blocks non admin members from listing role templates', function (): void {
    RoleTemplate::query()->create([
        'slug' => 'buyer_member',
        'name' => 'Buyer member',
        'description' => 'Read-only sourcing',
        'permissions' => ['rfqs.read'],
        'is_system' => true,
    ]);

    $company = Company::factory()->create();

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_member',
    ]);

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'buyer_member',
        'is_default' => true,
        'last_used_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($user);

    getJson('/api/company-role-templates')->assertForbidden();
});

it('returns cursor metadata when listing role templates', function (): void {
    foreach (range(1, 3) as $index) {
        RoleTemplate::query()->create([
            'slug' => 'role_'.$index,
            'name' => 'Role '.$index,
            'description' => 'Role '.$index.' description',
            'permissions' => ['rfqs.read'],
            'is_system' => true,
        ]);
    }

    $company = Company::factory()->create();

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'buyer_admin',
        'is_default' => true,
        'last_used_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($user);

    $firstPage = getJson('/api/company-role-templates?per_page=1');

    $firstPage
        ->assertOk()
        ->assertJsonPath('data.items', fn ($items) => count($items) === 1)
        ->assertJsonPath('data.meta.per_page', 1)
        ->assertJsonPath('meta.cursor.has_next', true);

    $cursor = $firstPage->json('data.meta.next_cursor');

    expect($cursor)->not->toBeNull();

    getJson('/api/company-role-templates?per_page=1&cursor='.urlencode((string) $cursor))
        ->assertOk()
        ->assertJsonPath('meta.cursor.has_prev', true);
});
