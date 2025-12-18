<?php

use App\Http\Requests\Invoice\CreateInvoiceFromPurchaseOrderRequest;
use App\Models\Company;
use App\Models\Supplier;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\Validator;

test('supplier validation scopes to active company context', function (): void {
    $buyerCompany = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    CompanyContext::forCompany($buyerCompany->id, function () use (&$buyerSupplier): void {
        $buyerSupplier = Supplier::factory()->create();
    });

    CompanyContext::forCompany($otherCompany->id, function () use (&$externalSupplier): void {
        $externalSupplier = Supplier::factory()->create();
    });

    CompanyContext::set($buyerCompany->id);

    $request = new CreateInvoiceFromPurchaseOrderRequest();
    $rules = $request->rules();

    $validValidator = Validator::make(
        ['supplier_id' => $buyerSupplier->id],
        ['supplier_id' => $rules['supplier_id']]
    );

    expect($validValidator->passes())->toBeTrue();

    $invalidValidator = Validator::make(
        ['supplier_id' => $externalSupplier->id],
        ['supplier_id' => $rules['supplier_id']]
    );

    expect($invalidValidator->fails())->toBeTrue();
});
