<?php

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

test('sending a purchase order delivers via email and webhook automatically', function (): void {
    $company = createSubscribedCompany();
    $supplier = Supplier::factory()->for($company)->create(['email' => 'supplier@example.test']);

    $purchaseOrder = PurchaseOrder::factory()
        ->for($company, 'company')
        ->for($supplier, 'supplier')
        ->create([
            'status' => 'draft',
        ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    actingAs($user);

    postJson("/api/purchase-orders/{$purchaseOrder->getKey()}/send", [
        'message' => 'Please confirm ASAP.',
    ])->assertOk();

    $purchaseOrder->refresh()->load('deliveries');

    expect($purchaseOrder->status)->toBe('sent');
    expect($purchaseOrder->deliveries)->toHaveCount(2);
    expect($purchaseOrder->deliveries->pluck('channel')->all())
        ->toEqualCanonicalizing(['email', 'webhook']);

    $emailDelivery = $purchaseOrder->deliveries->firstWhere('channel', 'email');
    expect($emailDelivery)->not()->toBeNull();
    expect($emailDelivery->recipients_to)->toEqual([$supplier->email]);
    expect($emailDelivery->message)->toBe('Please confirm ASAP.');

    $webhookDelivery = $purchaseOrder->deliveries->firstWhere('channel', 'webhook');
    expect($webhookDelivery)->not()->toBeNull();
    expect($webhookDelivery->recipients_to)->toBeNull();
});

test('sending fails when supplier email is missing', function (): void {
    $company = createSubscribedCompany();
    $supplier = Supplier::factory()->for($company)->create(['email' => null]);

    $purchaseOrder = PurchaseOrder::factory()
        ->for($company, 'company')
        ->for($supplier, 'supplier')
        ->create([
            'status' => 'draft',
        ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    actingAs($user);

    postJson("/api/purchase-orders/{$purchaseOrder->getKey()}/send", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['supplier']);
});

test('sending succeeds with override email when supplier email is missing', function (): void {
    $company = createSubscribedCompany();
    $supplier = Supplier::factory()->for($company)->create(['email' => null]);

    $purchaseOrder = PurchaseOrder::factory()
        ->for($company, 'company')
        ->for($supplier, 'supplier')
        ->create([
            'status' => 'draft',
        ]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'role' => 'buyer_admin',
    ]);

    actingAs($user);

    postJson("/api/purchase-orders/{$purchaseOrder->getKey()}/send", [
        'override_email' => 'ops@example.com',
        'message' => 'Sending with override.',
    ])->assertOk();

    $purchaseOrder->refresh()->load('deliveries');

    $emailDelivery = $purchaseOrder->deliveries->firstWhere('channel', 'email');
    expect($emailDelivery)->not()->toBeNull();
    expect($emailDelivery->recipients_to)->toEqual(['ops@example.com']);
});
