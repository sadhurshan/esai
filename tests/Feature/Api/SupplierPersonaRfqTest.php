<?php

use App\Enums\CompanySupplierStatus;
use App\Models\RFQ;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\User;
use App\Services\Auth\PersonaResolver;
use App\Support\CompanyContext;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

it('allows supplier personas to view invited rfqs while preserving buyer access', function (): void {
    $buyerCompany = createSubscribedCompany();
    $supplierCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    /** @var User $supplierOwner */
    $supplierOwner = User::factory()->owner()->create([
        'company_id' => $supplierCompany->id,
    ]);

    $supplierCompany->owner()->associate($supplierOwner);
    $supplierCompany->owner_user_id = $supplierOwner->id;
    $supplierCompany->save();

    $supplierOwner->companies()->attach($supplierCompany->id, [
        'role' => 'owner',
        'is_default' => true,
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
    ]);

    $supplier = CompanyContext::forCompany($supplierCompany->id, fn () => Supplier::factory()
        ->for($supplierCompany)
        ->create([
            'status' => 'approved',
        ]));

    $supplierOwner->forceFill([
        'supplier_capable' => true,
        'default_supplier_id' => $supplier->id,
    ])->save();

    CompanyContext::forCompany($buyerCompany->id, function () use ($buyerCompany, $supplier, $supplierOwner): void {
        SupplierContact::query()->create([
            'company_id' => $buyerCompany->id,
            'supplier_id' => $supplier->id,
            'user_id' => $supplierOwner->id,
        ]);
    });

    $invitedRfq = CompanyContext::forCompany($buyerCompany->id, fn () => RFQ::factory()->create([
        'company_id' => $buyerCompany->id,
        'status' => RFQ::STATUS_OPEN,
        'open_bidding' => false,
    ]));

    RfqInvitation::factory()->create([
        'rfq_id' => $invitedRfq->id,
        'supplier_id' => $supplier->id,
    ]);

    $otherSupplierCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    $otherSupplier = CompanyContext::forCompany($otherSupplierCompany->id, fn () => Supplier::factory()
        ->for($otherSupplierCompany)
        ->create([
            'status' => 'approved',
        ]));

    $hiddenRfq = CompanyContext::forCompany($buyerCompany->id, fn () => RFQ::factory()->create([
        'company_id' => $buyerCompany->id,
        'status' => RFQ::STATUS_OPEN,
        'open_bidding' => false,
    ]));

    RfqInvitation::factory()->create([
        'rfq_id' => $hiddenRfq->id,
        'supplier_id' => $otherSupplier->id,
    ]);

    $this->actingAs($supplierOwner);

    $personas = app(PersonaResolver::class)->resolve($supplierOwner->fresh());

    $supplierPersona = collect($personas)->first(function (array $persona) use ($buyerCompany, $supplier): bool {
        return $persona['type'] === 'supplier'
            && (int) Arr::get($persona, 'company_id') === $buyerCompany->id
            && (int) Arr::get($persona, 'supplier_id') === $supplier->id;
    });

    expect($supplierPersona)->not->toBeNull();

    $this->withSession(['active_persona' => $supplierPersona])
        ->getJson('/api/supplier/rfqs')
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', (string) $invitedRfq->id);

    $buyerPersona = collect($personas)->first(function (array $persona) use ($supplierCompany): bool {
        return $persona['type'] === 'buyer'
            && (int) Arr::get($persona, 'company_id') === $supplierCompany->id;
    });

    expect($buyerPersona)->not->toBeNull();

    CompanyContext::forCompany($supplierCompany->id, fn () => RFQ::factory()->create([
        'company_id' => $supplierCompany->id,
        'status' => RFQ::STATUS_OPEN,
    ]));

    $this->withSession(['active_persona' => $buyerPersona])
        ->getJson('/api/rfqs')
        ->assertOk()
        ->assertJsonPath('status', 'success');
});
