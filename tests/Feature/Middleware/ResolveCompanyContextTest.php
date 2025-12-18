<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\ResolveCompanyContext;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\User;
use App\Support\ActivePersona;
use App\Support\CompanyContext;
use App\Support\RequestPersonaResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ResolveCompanyContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_persona_sets_request_attributes(): void
    {
        $buyerCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $buyerCompany->id]);
        $supplier = CompanyContext::forCompany($buyerCompany->id, fn () => Supplier::factory()->create());

        CompanyContext::forCompany($buyerCompany->id, function () use ($buyerCompany, $supplier, $user): void {
            SupplierContact::factory()->create([
                'company_id' => $buyerCompany->id,
                'supplier_id' => $supplier->id,
                'user_id' => $user->id,
            ]);
        });

        $personaPayload = [
            'key' => 'supplier:'.$supplier->id,
            'type' => ActivePersona::TYPE_SUPPLIER,
            'company_id' => $buyerCompany->id,
            'supplier_id' => $supplier->id,
        ];

        $request = Request::create('/middleware-test', 'GET');
        $session = app('session')->driver();
        $session->start();
        $request->setLaravelSession($session);
        $request->setUserResolver(static fn () => $user);
        RequestPersonaResolver::remember($request, $personaPayload);

        $middleware = app(ResolveCompanyContext::class);

        $captured = [];
        $response = $middleware->handle($request, function (Request $handled) use (&$captured) {
            $captured = [
                'company_id' => $handled->attributes->get('company_id'),
                'active_persona' => $handled->attributes->get('active_persona'),
                'acting_supplier_id' => $handled->attributes->get('acting_supplier_id'),
            ];

            return response()->noContent();
        });

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame($buyerCompany->id, $captured['company_id']);
        $this->assertSame($supplier->id, $captured['acting_supplier_id']);
        $this->assertIsArray($captured['active_persona']);
        $this->assertSame($supplier->id, $captured['active_persona']['supplier_id']);
        $this->assertSame($buyerCompany->id, $captured['active_persona']['company_id']);
    }

    public function test_supplier_persona_attribute_used_when_session_missing(): void
    {
        $buyerCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $buyerCompany->id]);
        $supplier = CompanyContext::forCompany($buyerCompany->id, fn () => Supplier::factory()->create());

        CompanyContext::forCompany($buyerCompany->id, function () use ($buyerCompany, $supplier, $user): void {
            SupplierContact::factory()->create([
                'company_id' => $buyerCompany->id,
                'supplier_id' => $supplier->id,
                'user_id' => $user->id,
            ]);
        });

        $personaPayload = [
            'key' => 'supplier:'.$supplier->id,
            'type' => ActivePersona::TYPE_SUPPLIER,
            'company_id' => $buyerCompany->id,
            'supplier_id' => $supplier->id,
        ];

        $request = Request::create('/middleware-test', 'GET');
        $request->setUserResolver(static fn () => $user);
        $request->attributes->set('session.active_persona', $personaPayload);

        $middleware = app(ResolveCompanyContext::class);

        $captured = [];
        $response = $middleware->handle($request, function (Request $handled) use (&$captured) {
            $captured = [
                'company_id' => $handled->attributes->get('company_id'),
                'acting_supplier_id' => $handled->attributes->get('acting_supplier_id'),
            ];

            return response()->noContent();
        });

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame($buyerCompany->id, $captured['company_id']);
        $this->assertSame($supplier->id, $captured['acting_supplier_id']);
    }

    public function test_supplier_persona_header_applies_persona_context(): void
    {
        $buyerCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $buyerCompany->id]);
        $supplier = CompanyContext::forCompany($buyerCompany->id, fn () => Supplier::factory()->create());

        CompanyContext::forCompany($buyerCompany->id, function () use ($buyerCompany, $supplier, $user): void {
            SupplierContact::factory()->create([
                'company_id' => $buyerCompany->id,
                'supplier_id' => $supplier->id,
                'user_id' => $user->id,
            ]);
        });

        $personaPayload = [
            'key' => sprintf('supplier:%d:%d', $buyerCompany->id, $supplier->id),
            'type' => ActivePersona::TYPE_SUPPLIER,
            'company_id' => $buyerCompany->id,
            'supplier_id' => $supplier->id,
        ];

        $request = Request::create('/middleware-test', 'GET');
        $request->setUserResolver(static fn () => $user);
        $request->headers->set('X-Active-Persona', $personaPayload['key']);

        $middleware = app(ResolveCompanyContext::class);

        $captured = [];
        $response = $middleware->handle($request, function (Request $handled) use (&$captured) {
            $captured = [
                'company_id' => $handled->attributes->get('company_id'),
                'acting_supplier_id' => $handled->attributes->get('acting_supplier_id'),
            ];

            return response()->noContent();
        });

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame($buyerCompany->id, $captured['company_id']);
        $this->assertSame($supplier->id, $captured['acting_supplier_id']);
    }
}
