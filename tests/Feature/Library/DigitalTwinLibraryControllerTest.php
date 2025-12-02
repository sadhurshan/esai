<?php

use App\Enums\DigitalTwinAssetType;
use App\Models\Company;
use App\Models\CompanyFeatureFlag;
use App\Models\Customer;
use App\Models\DigitalTwin;
use App\Models\DigitalTwinAsset;
use App\Models\DigitalTwinCategory;
use App\Models\DigitalTwinSpec;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function createLibraryUser(string $role = 'buyer_admin', array $planOverrides = [], array $companyOverrides = []): User
{
    $plan = Plan::factory()->create(array_merge([
        'digital_twin_enabled' => true,
        'rfqs_per_month' => 25,
    ], $planOverrides));

    $company = Company::factory()->create(array_merge([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'status' => 'active',
    ], $companyOverrides));

    $customer = Customer::factory()->for($company)->create();

    Subscription::factory()->for($company)->create([
        'customer_id' => $customer->id,
        'stripe_status' => 'active',
    ]);

    return User::factory()->for($company)->create([
        'role' => $role,
    ]);
}

it('lists published digital twins with filters and categories', function () {
    Storage::fake('s3');

    $user = createLibraryUser();
    actingAs($user);

    $category = DigitalTwinCategory::factory()->create(['name' => 'Precision Machining']);
    $inactiveCategory = DigitalTwinCategory::factory()->create(['is_active' => false]);

    $visibleTwin = DigitalTwin::factory()
        ->published()
        ->create([
            'company_id' => null,
            'category_id' => $category->id,
            'tags' => ['cnc', 'aerospace'],
        ]);

    DigitalTwinSpec::factory()->for($visibleTwin)->create([
        'name' => 'Material',
        'value' => '6061-T6',
        'uom' => null,
    ]);

    DigitalTwinAsset::factory()->for($visibleTwin)->create([
        'type' => DigitalTwinAssetType::CAD,
        'is_primary' => true,
        'disk' => 's3',
    ]);

    DigitalTwin::factory()->published()->create([
        'category_id' => $inactiveCategory->id,
        'tags' => ['sheet_metal'],
    ]);

    DigitalTwin::factory()->create(['category_id' => $category->id]);

    $response = $this->getJson('/api/library/digital-twins?include[]=categories&has_asset=CAD');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $visibleTwin->id)
        ->assertJsonPath('data.items.0.asset_types.0', DigitalTwinAssetType::CAD->value)
        ->assertJsonPath('data.categories.0.id', $category->id)
        ->assertJsonPath('data.meta.per_page', 20);
});

it('prevents supplier roles from accessing the library', function () {
    $supplierUser = createLibraryUser('supplier_admin');
    actingAs($supplierUser);

    $response = $this->getJson('/api/library/digital-twins');

    $response->assertForbidden()
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Buyer access required.');
});

it('enforces plan gating when digital twin access is disabled', function () {
    $user = createLibraryUser('buyer_admin', ['digital_twin_enabled' => false]);
    actingAs($user);

    $response = $this->getJson('/api/library/digital-twins');

    $response->assertStatus(402)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('errors.code', 'digital_twin_disabled');
});

it('allows growth plan codes to access the library even when the column is disabled', function () {
    $user = createLibraryUser('buyer_admin', [
        'code' => 'growth',
        'digital_twin_enabled' => false,
    ]);

    DigitalTwin::factory()->published()->create();

    actingAs($user);

    $response = $this->getJson('/api/library/digital-twins');

    $response->assertOk()
        ->assertJsonPath('status', 'success');
});

it('honors company feature flag overrides for digital twins', function () {
    $user = createLibraryUser('buyer_admin', ['digital_twin_enabled' => false]);

    CompanyFeatureFlag::factory()->create([
        'company_id' => $user->company_id,
        'key' => 'digital_twin_enabled',
        'value' => ['enabled' => true],
    ]);

    DigitalTwin::factory()->published()->create();

    actingAs($user);

    $response = $this->getJson('/api/library/digital-twins');

    $response->assertOk()
        ->assertJsonPath('status', 'success');
});

it('returns rfq draft payload populated from a digital twin', function () {
    Storage::fake('s3');

    $user = createLibraryUser();
    actingAs($user);

    $twin = DigitalTwin::factory()->published()->create([
        'company_id' => null,
        'title' => 'Aero Bracket',
        'summary' => 'Structural bracket for UAV payload bay.',
    ]);

    collect([
        ['name' => 'Manufacturing Method', 'value' => 'CNC Machining'],
        ['name' => 'Material', 'value' => '7075 Aluminum'],
        ['name' => 'Quantity', 'value' => '250'],
        ['name' => 'UoM', 'value' => 'ea', 'uom' => 'ea'],
        ['name' => 'Target Price', 'value' => '42.50'],
    ])->each(fn (array $spec) => DigitalTwinSpec::factory()->for($twin)->create($spec));

    DigitalTwinAsset::factory()->for($twin)->create([
        'type' => DigitalTwinAssetType::PDF,
        'is_primary' => true,
        'disk' => 's3',
    ]);

    $response = $this->postJson("/api/library/digital-twins/{$twin->id}/use-for-rfq");

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.digital_twin.id', $twin->id)
        ->assertJsonPath('data.draft.digital_twin_id', $twin->id)
        ->assertJsonPath('data.draft.lines.0.method', 'CNC Machining')
        ->assertJsonPath('data.draft.lines.0.material', '7075 Aluminum')
        ->assertJsonPath('data.draft.lines.0.quantity', 250)
        ->assertJsonPath('data.draft.lines.0.uom', 'ea')
        ->assertJsonCount(1, 'data.draft.attachments');
});
