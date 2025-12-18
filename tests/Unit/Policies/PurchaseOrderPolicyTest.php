<?php

use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\Supplier;
use App\Models\User;
use App\Policies\PurchaseOrderPolicy;
use App\Support\ActivePersona;
use App\Support\ActivePersonaContext;

it('allows an owner acting via supplier persona to view purchase orders', function (): void {
    $purchaseOrder = new PurchaseOrder(['company_id' => 222]);

    $supplier = new Supplier(['company_id' => 777]);
    $quote = new Quote();
    $quote->setRelation('supplier', $supplier);
    $purchaseOrder->setRelation('quote', $quote);

    $user = new User([
        'id' => 1,
        'company_id' => 111,
        'role' => 'owner',
    ]);

    $persona = ActivePersona::fromArray([
        'key' => 'supplier:222:555',
        'type' => ActivePersona::TYPE_SUPPLIER,
        'company_id' => 222,
        'supplier_id' => 555,
        'supplier_company_id' => 777,
    ]);

    expect($persona)->not()->toBeNull();

    try {
        ActivePersonaContext::set($persona);

        $policy = app(PurchaseOrderPolicy::class);

        expect($policy->view($user, $purchaseOrder))->toBeTrue();
    } finally {
        ActivePersonaContext::clear();
    }
});

it('allows supplier personas with privileged roles to view purchase order events', function (): void {
    $purchaseOrder = new PurchaseOrder(['company_id' => 333]);

    $supplier = new Supplier(['company_id' => 888]);
    $purchaseOrder->setRelation('supplier', $supplier);

    $user = new User([
        'id' => 2,
        'company_id' => 222,
        'role' => 'owner',
    ]);

    $persona = ActivePersona::fromArray([
        'key' => 'supplier:222:777',
        'type' => ActivePersona::TYPE_SUPPLIER,
        'company_id' => 222,
        'supplier_id' => 777,
        'supplier_company_id' => 888,
    ]);

    expect($persona)->not()->toBeNull();

    try {
        ActivePersonaContext::set($persona);

        $policy = app(PurchaseOrderPolicy::class);

        expect($policy->viewEvents($user, $purchaseOrder))->toBeTrue();
    } finally {
        ActivePersonaContext::clear();
    }
});
