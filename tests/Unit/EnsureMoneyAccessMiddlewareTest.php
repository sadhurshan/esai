<?php

use App\Http\Middleware\EnsureMoneyAccess;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\User;
use App\Support\ActivePersona;
use App\Support\ActivePersonaContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

test('supplier personas can read money settings without money permissions', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $supplier = Supplier::factory()->for($company)->create();

    SupplierContact::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'user_id' => $user->id,
    ]);

    $persona = ActivePersona::fromArray([
        'key' => 'supplier-'.$supplier->id,
        'type' => ActivePersona::TYPE_SUPPLIER,
        'company_id' => $company->id,
        'company_name' => $company->name,
        'supplier_id' => $supplier->id,
        'supplier_name' => $supplier->name,
    ]);

    expect($persona)->not->toBeNull();

    ActivePersonaContext::set($persona);

    try {
        $request = Request::create('/api/money/settings', 'GET');
        $request->setUserResolver(static fn () => $user);

        $middleware = app(EnsureMoneyAccess::class);

        $response = $middleware->handle($request, static fn () => new Response('ok'));

        expect($response->getContent())->toBe('ok');
    } finally {
        ActivePersonaContext::clear();
    }
});

test('supplier personas are blocked from billing money scope', function (): void {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $supplier = Supplier::factory()->for($company)->create();

    SupplierContact::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'user_id' => $user->id,
    ]);

    $persona = ActivePersona::fromArray([
        'key' => 'supplier-'.$supplier->id,
        'type' => ActivePersona::TYPE_SUPPLIER,
        'company_id' => $company->id,
        'company_name' => $company->name,
        'supplier_id' => $supplier->id,
        'supplier_name' => $supplier->name,
    ]);

    expect($persona)->not->toBeNull();

    ActivePersonaContext::set($persona);

    try {
        $request = Request::create('/api/money/fx', 'GET');
        $request->setUserResolver(static fn () => $user);

        $middleware = app(EnsureMoneyAccess::class);

        $response = $middleware->handle($request, static fn () => new Response('ok'), 'billing');

        expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    } finally {
        ActivePersonaContext::clear();
    }
});
