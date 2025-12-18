<?php

namespace Tests\Feature\Api\Rfq;

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Models\Company;
use App\Models\RFQ;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\User;
use App\Services\Auth\PersonaResolver;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class SupplierRfqInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_invited_rfq_is_listed_for_supplier_company(): void
    {
        $supplierCompany = Company::factory()->create([
            'status' => CompanyStatus::Active,
            'supplier_status' => CompanySupplierStatus::Approved,
        ]);

        $supplierUser = User::factory()->create([
            'company_id' => $supplierCompany->id,
            'role' => 'owner',
        ]);

        CompanyContext::forCompany($supplierCompany->id, fn () => Supplier::factory()->create());

        $buyerCompany = Company::factory()->create([
            'status' => CompanyStatus::Active,
        ]);

        $rfq = CompanyContext::forCompany($buyerCompany->id, fn () => RFQ::factory()->create([
            'status' => RFQ::STATUS_OPEN,
        ]));

        $supplier = CompanyContext::bypass(fn () => Supplier::query()->where('company_id', $supplierCompany->id)->firstOrFail());

        CompanyContext::forCompany($buyerCompany->id, function () use ($rfq, $supplier, $supplierUser, $buyerCompany): void {
            RfqInvitation::factory()->create([
                'rfq_id' => $rfq->id,
                'supplier_id' => $supplier->id,
            ]);

            SupplierContact::query()->create([
                'company_id' => $buyerCompany->id,
                'supplier_id' => $supplier->id,
                'user_id' => $supplierUser->id,
            ]);
        });

        $personas = app(PersonaResolver::class)->resolve($supplierUser->fresh());

        $supplierPersona = collect($personas)->first(function (array $persona) use ($buyerCompany, $supplier): bool {
            return $persona['type'] === 'supplier'
                && (int) Arr::get($persona, 'company_id') === $buyerCompany->id
                && (int) Arr::get($persona, 'supplier_id') === $supplier->id;
        });

        $this->assertNotNull($supplierPersona, 'Supplier persona should be available for invited contact.');

        $response = $this->actingAs($supplierUser)
            ->withSession(['active_persona' => $supplierPersona])
            ->getJson('/api/supplier/rfqs');

        $response->assertOk();
        $response->assertJsonPath('data.items.0.id', (string) $rfq->id);
    }
}
