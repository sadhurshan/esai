<?php

use App\Models\Company;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\RFQ;
use App\Models\RfqItem;
use App\Models\SavedSearch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

function prepareSearchContext(array $planOverrides = [], array $companyOverrides = [], array $userOverrides = []): array
{
    $plan = Plan::factory()->create(array_merge([
        'code' => 'search-plan-'.Str::uuid()->toString(),
        'global_search_enabled' => true,
    ], $planOverrides));

    $company = Company::factory()->create(array_merge([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
        'status' => 'active',
    ], $companyOverrides));

    $user = User::factory()->create(array_merge([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ], $userOverrides));

    return [$plan, $company, $user];
}

it('returns search results across suppliers, parts, rfqs, purchase orders, invoices, and documents', function (): void {
    [, $company, $user] = prepareSearchContext();

    /** @var Supplier $supplier */
    $supplier = Supplier::factory()->create([
        'company_id' => $company->id,
        'name' => 'Omega Gear Works',
        'status' => 'approved',
        'capabilities' => [
            'materials' => ['Omega Steel'],
            'methods' => ['CNC Milling'],
        ],
    ]);

    /** @var RFQ $rfq */
    $rfq = RFQ::factory()->create([
        'company_id' => $company->id,
        'title' => 'Omega Gear Housing',
        'number' => 'RFQ-OMEGA-1',
        'status' => 'open',
        'publish_at' => now()->subDay(),
    ]);

    RfqItem::factory()->create([
        'rfq_id' => $rfq->id,
        'part_name' => 'Omega Gear Shaft',
        'spec' => 'Omega hardened 4140 steel',
    ]);

    /** @var PurchaseOrder $purchaseOrder */
    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'po_number' => 'PO-OMEGA-42',
        'status' => 'sent',
    ]);

    /** @var Invoice $invoice */
    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'purchase_order_id' => $purchaseOrder->id,
        'supplier_id' => $supplier->id,
        'invoice_number' => 'INV-OMEGA-42',
        'status' => 'pending',
    ]);

    Document::create([
        'company_id' => $company->id,
        'documentable_type' => PurchaseOrder::class,
        'documentable_id' => $purchaseOrder->id,
        'kind' => 'po',
        'category' => 'technical',
        'visibility' => 'company',
        'version_number' => 1,
        'expires_at' => null,
        'path' => 'docs/omega.pdf',
        'filename' => 'Omega Gear Specs.pdf',
        'mime' => 'application/pdf',
        'size_bytes' => 1024,
        'hash' => null,
        'watermark' => [],
        'meta' => ['description' => 'Omega specification pack'],
    ]);

    actingAs($user);

    $response = getJson('/api/search?q=Omega');

    $response->assertOk();
    expect($response->json('status'))->toBe('success');

    $items = collect($response->json('data.items'));

    expect($items->pluck('type')->all())
        ->toContain('supplier')
        ->toContain('part')
        ->toContain('rfq')
        ->toContain('purchase_order')
        ->toContain('invoice')
        ->toContain('document');

    expect($response->json('data.meta.total'))->toBeGreaterThanOrEqual(6);
});

it('filters search results by status and date range', function (): void {
    [, $company, $user] = prepareSearchContext();

    RFQ::factory()->create([
        'company_id' => $company->id,
        'title' => 'Valve Housing Assembly',
        'number' => 'RFQ-VALVE-OLD',
        'status' => 'closed',
        'publish_at' => now()->subMonths(2),
    ]);

    RFQ::factory()->create([
        'company_id' => $company->id,
        'title' => 'Valve Core Omega',
        'number' => 'RFQ-VALVE-NEW',
        'status' => 'open',
        'publish_at' => now()->subDay(),
    ]);

    actingAs($user);

    $response = getJson(sprintf(
        '/api/search?q=Valve&types=rfq&status=open&date_from=%s&date_to=%s',
        now()->subDays(2)->toDateString(),
        now()->toDateString()
    ));

    $response->assertOk();
    $items = collect($response->json('data.items'));

    expect($items)->toHaveCount(1);
    expect($items->first()['identifier'] ?? null)->toBe('RFQ-VALVE-NEW');
});

it('supports creating, updating, listing, and deleting saved searches', function (): void {
    [, $company, $user] = prepareSearchContext();

    actingAs($user);

    $create = postJson('/api/saved-searches', [
        'name' => 'Omega Watch',
        'q' => 'omega',
        'entity_types' => ['supplier', 'rfq'],
        'filters' => [
            'status' => ['open'],
            'tags' => ['aerospace'],
        ],
        'tags' => 'favorite',
    ]);

    $create->assertCreated();

    $savedId = $create->json('data.id');
    expect($savedId)->not->toBeNull();

    $list = getJson('/api/saved-searches');
    $list->assertOk();
    expect(collect($list->json('data.items')))->toHaveCount(1);

    $update = putJson('/api/saved-searches/'.$savedId, [
        'name' => 'Omega Updated',
        'tags' => 'archived',
    ]);

    $update->assertOk();
    expect($update->json('data.name'))->toBe('Omega Updated');
    expect($update->json('data.tags'))->toBe('archived');

    $duplicate = postJson('/api/saved-searches', [
        'name' => 'Omega Updated',
        'q' => 'omega',
    ]);

    $duplicate->assertStatus(422);
    expect($duplicate->json('errors.name'))->not->toBeNull();

    $delete = deleteJson('/api/saved-searches/'.$savedId);
    $delete->assertOk();
    expect(SavedSearch::find($savedId))->toBeNull();
    expect(SavedSearch::withTrashed()->find($savedId))->not()->toBeNull();
});

it('returns 403 when plan disables global search', function (): void {
    [$plan, $company, $user] = prepareSearchContext(['global_search_enabled' => false]);

    actingAs($user);

    $response = getJson('/api/search?q=test');

    $response->assertStatus(403);
    expect($response->json('status'))->toBe('error');
});

it('validates blank queries', function (): void {
    [, , $user] = prepareSearchContext();

    actingAs($user);

    $response = getJson('/api/search?q=');

    $response->assertStatus(422);
    expect($response->json('status'))->toBe('error');
});
