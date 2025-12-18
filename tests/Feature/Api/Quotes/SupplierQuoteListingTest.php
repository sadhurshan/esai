<?php

use App\Models\Quote;
use App\Models\RFQ;
use App\Support\CompanyContext;
use Illuminate\Testing\Fluent\AssertableJson;
use function Pest\Laravel\actingAs;

it('lists submitted quotes for the active supplier persona', function (): void {
    $buyer = createSubscribedCompany();
    $supplierContext = createSupplierPersonaForBuyer($buyer);

    $rfq = CompanyContext::forCompany($buyer->id, static fn () => RFQ::factory()->create([
        'company_id' => $buyer->id,
        'status' => 'open',
    ]));

    $quote = CompanyContext::forCompany($buyer->id, function () use ($buyer, $supplierContext, $rfq): Quote {
        return Quote::query()->create([
            'company_id' => $buyer->id,
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplierContext['supplier']->id,
            'status' => 'submitted',
            'currency' => 'USD',
            'unit_price' => '125.50',
            'subtotal' => '125.50',
            'subtotal_minor' => 12550,
            'tax_amount' => '0.00',
            'tax_amount_minor' => 0,
            'total_price' => '125.50',
            'total_price_minor' => 12550,
            'lead_time_days' => 12,
            'revision_no' => 1,
            'submitted_at' => now(),
        ]);
    });

    actingAs($supplierContext['user']);

    $response = $this
        ->withHeaders(['X-Active-Persona' => $supplierContext['persona']['key']])
        ->getJson('/api/supplier/quotes');

    $response
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('status', 'success')
            ->has('data.items', 1)
            ->where('data.items.0.id', (string) $quote->id)
            ->where('data.items.0.rfq_id', $rfq->id)
            ->where('data.items.0.supplier_id', $supplierContext['supplier']->id)
            ->where('data.items.0.status', 'submitted')
            ->etc()
        );
});
