<?php

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Document;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderEvent;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Session;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    Storage::fake('local');
    config(['documents.disk' => 'local']);
});

function companyWithActivePlan(): Company
{
    $plan = Plan::query()->firstWhere('code', 'community')
        ?? Plan::factory()->create([
            'code' => 'community',
            'price_usd' => 0,
        ]);

    return Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);
}

it('cancels draft purchase orders for the buyer company', function (): void {
    $company = companyWithActivePlan();
    $user = User::factory()->create(['company_id' => $company->id]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'draft',
        'subtotal_minor' => 10_000,
        'tax_amount_minor' => 800,
        'total_minor' => 10_800,
    ]);

    PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'line_no' => 1,
        'quantity' => 10,
        'unit_price' => 100,
        'unit_price_minor' => 10_000,
    ]);

    actingAs($user);

    postJson("/api/purchase-orders/{$purchaseOrder->id}/cancel")
        ->assertOk()
        ->assertJsonPath('message', 'Purchase order cancelled.')
        ->assertJsonPath('data.status', 'cancelled');

    $purchaseOrder->refresh();

    expect($purchaseOrder->status)->toBe('cancelled');
    expect($purchaseOrder->cancelled_at)->not->toBeNull();
});

it('records audit logs when cancelling purchase orders', function (): void {
    $company = companyWithActivePlan();
    $user = User::factory()->create(['company_id' => $company->id]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'draft',
    ]);

    actingAs($user);

    postJson("/api/purchase-orders/{$purchaseOrder->id}/cancel")
        ->assertOk();

    expect(
        AuditLog::query()
            ->where('entity_type', PurchaseOrder::class)
            ->where('entity_id', $purchaseOrder->id)
            ->where('action', 'updated')
            ->exists()
    )->toBeTrue();
});

it('prevents other companies from cancelling purchase orders they do not own', function (): void {
    $buyerCompany = companyWithActivePlan();
    $otherCompany = companyWithActivePlan();
    $buyer = User::factory()->create(['company_id' => $buyerCompany->id]);
    $intruder = User::factory()->create(['company_id' => $otherCompany->id]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $buyerCompany->id,
    ]);

    actingAs($intruder);

    postJson("/api/purchase-orders/{$purchaseOrder->id}/cancel")
        ->assertForbidden();

    actingAs($buyer);
    postJson("/api/purchase-orders/{$purchaseOrder->id}/cancel")
        ->assertOk();
});

it('returns an api envelope when company context is missing on cancellation', function (): void {
    $user = User::factory()->create(['company_id' => null]);
    $purchaseOrder = PurchaseOrder::factory()->create();

    actingAs($user);

    postJson("/api/purchase-orders/{$purchaseOrder->id}/cancel")
        ->assertForbidden()
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'No active company membership.');
});

it('exports purchase orders to PDF and provides a download URL', function (): void {
    $company = companyWithActivePlan();
    $user = User::factory()->create(['company_id' => $company->id]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'sent',
        'subtotal_minor' => 50_000,
        'tax_amount_minor' => 4_000,
        'total_minor' => 54_000,
    ]);

    PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'line_no' => 1,
        'quantity' => 5,
        'unit_price' => 100,
        'unit_price_minor' => 10_000,
    ]);

    actingAs($user);

    $response = postJson("/api/purchase-orders/{$purchaseOrder->id}/export")
        ->assertOk()
        ->assertJsonPath('message', 'Purchase order PDF ready.')
        ->assertJsonStructure([
            'data' => [
                'document' => ['id', 'filename', 'version', 'download_url'],
                'download_url',
            ],
        ]);

    $purchaseOrder->refresh();

    expect($purchaseOrder->pdf_document_id)->not->toBeNull();

    $documentId = $response->json('data.document.id');
    expect(Document::query()->whereKey($documentId)->exists())->toBeTrue();
});

it('denies listing purchase orders without orders read permission', function (): void {
    $company = companyWithActivePlan();
    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_requester',
    ]);

    actingAs($user);

    getJson('/api/purchase-orders')
        ->assertForbidden()
        ->assertJsonPath('message', 'Orders access required.');
});

it('denies cancelling purchase orders without orders write permission', function (): void {
    $company = companyWithActivePlan();
    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_member',
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'draft',
    ]);

    actingAs($user);

    postJson("/api/purchase-orders/{$purchaseOrder->id}/cancel")
        ->assertForbidden()
        ->assertJsonPath('message', 'Orders write access required.');
});

it('requires an active subscription before cancelling purchase orders', function (): void {
    $plan = Plan::factory()->create([
        'code' => 'growth',
        'price_usd' => 2400,
    ]);

    $company = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'status' => 'draft',
    ]);

    actingAs($user);

    postJson("/api/purchase-orders/{$purchaseOrder->id}/cancel")
        ->assertStatus(402)
        ->assertJsonPath('message', 'Upgrade required')
        ->assertJsonPath('errors.code', 'subscription_inactive');
});

it('lists purchase orders with cursor pagination metadata', function (): void {
    $company = companyWithActivePlan();
    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    $orders = PurchaseOrder::factory()->count(3)->sequence(
        ['company_id' => $company->id, 'created_at' => now()->subDays(3)],
        ['company_id' => $company->id, 'created_at' => now()->subDays(2)],
        ['company_id' => $company->id, 'created_at' => now()->subDay()]
    )->create();

    actingAs($user);

    $firstPage = getJson('/api/purchase-orders?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('meta.cursor.has_next', true)
        ->assertJsonPath('meta.cursor.has_prev', false)
        ->json();

    $nextCursor = Arr::get($firstPage, 'meta.cursor.next_cursor');
    expect($nextCursor)->not->toBeNull();

    getJson('/api/purchase-orders?per_page=2&cursor='.$nextCursor)
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('meta.cursor.has_next', false)
        ->assertJsonPath('meta.cursor.has_prev', true)
        ->assertJsonStructure([
            'data' => [
                'meta' => ['next_cursor', 'prev_cursor', 'per_page'],
            ],
        ]);
});

it('paginates purchase order events using cursors', function (): void {
    $company = companyWithActivePlan();
    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $company->id,
    ]);

    foreach ([5, 4, 3] as $minutesAgo) {
        PurchaseOrderEvent::create([
            'purchase_order_id' => $purchaseOrder->id,
            'event_type' => 'timeline',
            'summary' => 'Event '.$minutesAgo,
            'description' => 'Event at minute '.$minutesAgo,
            'meta' => [],
            'occurred_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    actingAs($user);

    $first = getJson("/api/purchase-orders/{$purchaseOrder->id}/events?per_page=2")
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('meta.cursor.has_next', true)
        ->assertJsonPath('meta.cursor.has_prev', false)
        ->json();

    $cursor = Arr::get($first, 'meta.cursor.next_cursor');
    expect($cursor)->not->toBeNull();

    getJson("/api/purchase-orders/{$purchaseOrder->id}/events?per_page=2&cursor={$cursor}")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('meta.cursor.has_next', false)
        ->assertJsonPath('meta.cursor.has_prev', true);
});

it('allows supplier personas to view purchase orders addressed to them', function (): void {
    $buyerCompany = companyWithActivePlan();
    $supplierCompany = companyWithActivePlan();

    $user = User::factory()->create([
        'company_id' => $buyerCompany->id,
        'role' => 'owner',
    ]);

    $supplier = Supplier::factory()->create([
        'company_id' => $supplierCompany->id,
    ]);

    SupplierContact::factory()->create([
        'company_id' => $buyerCompany->id,
        'supplier_id' => $supplier->id,
        'user_id' => $user->id,
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'company_id' => $buyerCompany->id,
        'supplier_id' => $supplier->id,
        'status' => 'sent',
    ]);

    Session::start();
    session()->put('active_persona', [
        'key' => sprintf('supplier:%d:%d', $buyerCompany->id, $supplier->id),
        'type' => 'supplier',
        'company_id' => $buyerCompany->id,
        'company_name' => $buyerCompany->name,
        'supplier_id' => $supplier->id,
        'supplier_name' => $supplier->name,
        'supplier_company_id' => $supplierCompany->id,
        'supplier_company_name' => $supplierCompany->name,
        'role' => 'supplier_admin',
        'is_default' => false,
    ]);

    actingAs($user);

    getJson("/api/purchase-orders/{$purchaseOrder->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $purchaseOrder->id);
});
