<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

it('enforces pagination envelopes on collection responses and money field shape on resources', function (): void {
    Currency::query()->updateOrCreate([
        'code' => 'USD',
    ], [
        'name' => 'US Dollar',
        'minor_unit' => 2,
        'symbol' => '$',
    ]);

    $plan = Plan::factory()->create([
        'code' => 'plan-'.Str::lower(Str::random(8)),
        'rfqs_per_month' => 50,
        'invoices_per_month' => 50,
        'users_max' => 25,
        'storage_gb' => 25,
    ]);

    $company = Company::factory()->for($plan)->create([
        'plan_code' => $plan->code,
    ]);

    Subscription::factory()->for($company)->create([
        'stripe_status' => 'active',
    ]);

    $user = User::factory()->for($company)->create([
        'role' => 'buyer_admin',
    ]);

    $purchaseOrder = PurchaseOrder::factory()->for($company)->create([
        'status' => 'sent',
        'currency' => 'USD',
        'subtotal' => 123.45,
        'tax_amount' => 6.78,
        'total' => 130.23,
        'subtotal_minor' => 12345,
        'tax_amount_minor' => 678,
        'total_minor' => 13023,
    ]);

    PurchaseOrderLine::factory()->for($purchaseOrder)->create([
        'unit_price' => 12.34,
        'quantity' => 5,
    ]);

    actingAs($user);

    $indexResponse = getJson('/api/purchase-orders');

    $indexResponse
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'items',
                'meta' => ['total', 'per_page', 'current_page', 'last_page'],
            ],
        ]);

    $showResponse = getJson("/api/purchase-orders/{$purchaseOrder->id}");

    $showResponse
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonPath('data.total_minor', 13023)
        ->assertJsonPath('data.tax_amount_minor', 678)
        ->assertJsonPath('data.subtotal_minor', 12345);

    $payload = $showResponse->json('data');

    expect($payload['total'])
        ->toBe(sprintf('%.2f', $payload['total_minor'] / 100));

    expect($payload['subtotal'])
        ->toBe(sprintf('%.2f', $payload['subtotal_minor'] / 100));

    expect($payload['tax_amount'])
        ->toBe(sprintf('%.2f', $payload['tax_amount_minor'] / 100));
});
