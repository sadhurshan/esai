<?php

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Document;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

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
