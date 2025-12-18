<?php

namespace Tests\Unit\Support\Audit;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ActivePersona;
use App\Support\ActivePersonaContext;
use App\Support\Audit\AuditLogger;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_persona_metadata_from_context(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $supplier = CompanyContext::forCompany($company->id, fn () => Supplier::factory()->create());

        $persona = ActivePersona::fromArray([
            'key' => 'supplier:'.$supplier->id,
            'type' => ActivePersona::TYPE_SUPPLIER,
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
        ]);
        ActivePersonaContext::set($persona);

        $this->actingAs($user);

        $logger = new AuditLogger();
        $logger->created($supplier);

        $log = AuditLog::query()->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('supplier', $log->persona_type);
        $this->assertSame($company->id, $log->persona_company_id);
        $this->assertSame($supplier->id, $log->acting_supplier_id);

        ActivePersonaContext::clear();
    }
}
