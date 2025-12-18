<?php

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\Notification;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SupplierPersonaService;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SupplierPersonaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Event::fake();
    }

    public function test_notifies_owner_when_new_contact_created(): void
    {
        $supplierCompany = Company::factory()->create();
        $buyerCompany = Company::factory()->create(['name' => 'Acme Manufacturing']);
        $owner = User::factory()->create([
            'company_id' => $supplierCompany->id,
            'supplier_capable' => false,
            'default_supplier_id' => null,
        ]);
        $supplierCompany->owner()->associate($owner);
        $supplierCompany->owner_user_id = $owner->id;
        $supplierCompany->save();

        $supplier = CompanyContext::forCompany($supplierCompany->id, fn () => Supplier::factory()
            ->for($supplierCompany)
            ->create(['name' => 'Forge Works']));

        $service = app(SupplierPersonaService::class);

        $service->ensureBuyerContact($supplier, $buyerCompany->id, $buyerCompany);

        $contact = $supplier->contacts()->where('company_id', $buyerCompany->id)->where('user_id', $owner->id)->first();
        $this->assertNotNull($contact, 'Supplier contact should be created for invited buyer');

        $this->assertTrue($owner->fresh()->supplier_capable);
        $this->assertSame($supplier->id, $owner->fresh()->default_supplier_id);

        $notification = Notification::query()->where('user_id', $owner->id)->first();

        $this->assertNotNull($notification, 'Notification should be sent to supplier owner');
        $this->assertSame('persona.supplier.invited', $notification->event_type);
        $this->assertSame('Forge Works', $notification->meta['supplier_name'] ?? null);
        $this->assertSame('Acme Manufacturing', $notification->meta['buyer_company_name'] ?? null);
        $this->assertSame('/app/rfqs', $notification->meta['cta_url'] ?? null);

        // Ensure duplicate invitations do not spam notifications.
        $service->ensureBuyerContact($supplier, $buyerCompany->id, $buyerCompany);
        $this->assertSame(1, Notification::query()->where('user_id', $owner->id)->count());
    }

    public function test_skips_notification_when_disabled(): void
    {
        $supplierCompany = Company::factory()->create();
        $buyerCompany = Company::factory()->create();
        $owner = User::factory()->create([
            'company_id' => $supplierCompany->id,
        ]);
        $supplierCompany->owner()->associate($owner);
        $supplierCompany->owner_user_id = $owner->id;
        $supplierCompany->save();

        $supplier = CompanyContext::forCompany($supplierCompany->id, fn () => Supplier::factory()
            ->for($supplierCompany)
            ->create());

        $service = app(SupplierPersonaService::class);

        $service->ensureBuyerContact($supplier, $buyerCompany->id, $buyerCompany, false);

        $this->assertEquals(0, Notification::query()->count());
    }
}
