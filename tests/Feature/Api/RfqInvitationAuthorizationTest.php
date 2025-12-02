<?php

use App\Enums\CompanySupplierStatus;
use App\Models\RFQ;
use App\Models\RfqInvitation;
use App\Models\RfqItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('returns forbidden when RFQ belongs to another company', function (): void {
    $company = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    $foreignCompany = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved->value,
    ]);

    $owner = User::factory()->owner()->create([
        'company_id' => $company->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'role' => $owner->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rfq = RFQ::factory()->for($foreignCompany)->create([
        'status' => 'open',
        'created_by' => User::factory()->for($foreignCompany)->create()->id,
    ]);

    $supplier = Supplier::factory()->create([
        'status' => 'approved',
    ]);

    actingAs($owner);

    $response = $this->postJson("/api/rfqs/{$rfq->id}/invitations", [
        'supplier_ids' => [$supplier->id],
    ]);

    $response->assertForbidden();

    expect(RfqInvitation::count())->toBe(0);
});

it('invites suppliers when the company is approved', function (): void {
    $company = createSubscribedCompany([
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'is_verified' => true,
    ]);

    $owner = User::factory()->owner()->create([
        'company_id' => $company->id,
    ]);

    DB::table('company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'role' => $owner->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rfq = RFQ::factory()->for($company)->create([
        'status' => 'open',
        'created_by' => $owner->id,
    ]);

    $supplier = Supplier::factory()->for($company)->create([
        'status' => 'approved',
    ]);

    RfqItem::factory()->create([
        'rfq_id' => $rfq->id,
    ]);

    actingAs($owner);

    $response = $this->postJson("/api/rfqs/{$rfq->id}/invitations", [
        'supplier_ids' => [$supplier->id],
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data.items');

    expect(RfqInvitation::count())->toBe(1)
        ->and(RfqInvitation::first()->supplier_id)->toBe($supplier->id);
});
