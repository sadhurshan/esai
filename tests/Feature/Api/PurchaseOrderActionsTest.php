<?php

use App\Models\Company;
use App\Models\Document;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    Storage::fake('local');
    config(['documents.disk' => 'local']);
});

it('cancels draft purchase orders for the buyer company', function (): void {
    $company = Company::factory()->create();
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

it('prevents other companies from cancelling purchase orders they do not own', function (): void {
    $buyerCompany = Company::factory()->create();
    $otherCompany = Company::factory()->create();
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

it('exports purchase orders to PDF and provides a download URL', function (): void {
    $company = Company::factory()->create();
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
