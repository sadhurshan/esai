<?php

use App\Enums\CompanyStatus;
use App\Enums\CompanySupplierStatus;
use App\Models\Company;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\RFQ;
use App\Models\RfqDeadlineExtension;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('allows buyer admins to extend deadlines and notifies suppliers', function (): void {
    Storage::fake('public');

    $plan = Plan::factory()->create([
        'code' => 'community',
        'price_usd' => 0,
    ]);

    $buyerCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $buyerUser = User::factory()->create([
        'company_id' => $buyerCompany->id,
        'role' => 'buyer_admin',
    ]);

    DB::table('company_user')->insert([
        'company_id' => $buyerCompany->id,
        'user_id' => $buyerUser->id,
        'role' => $buyerUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplierCompany = Company::factory()->create([
        'status' => CompanyStatus::Active->value,
        'supplier_status' => CompanySupplierStatus::Approved->value,
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $supplierUser = User::factory()->create([
        'company_id' => $supplierCompany->id,
        'role' => 'supplier_admin',
    ]);

    DB::table('company_user')->insert([
        'company_id' => $supplierCompany->id,
        'user_id' => $supplierUser->id,
        'role' => $supplierUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $supplier = Supplier::factory()->for($supplierCompany)->create();

    $rfq = RFQ::factory()->for($buyerCompany)->create([
        'status' => RFQ::STATUS_OPEN,
        'due_at' => now()->addDays(2),
        'close_at' => now()->addDays(2),
        'created_by' => $buyerUser->id,
    ]);

    RfqInvitation::create([
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplier->id,
        'invited_by' => $buyerUser->id,
        'status' => RfqInvitation::STATUS_PENDING,
    ]);

    actingAs($buyerUser);

    $newDue = now()->addDays(7)->startOfMinute();

    $response = $this->postJson("/api/rfqs/{$rfq->id}/extend-deadline", [
        'new_due_at' => $newDue->toIso8601String(),
        'reason' => 'Design scope increased, suppliers need more time.',
        'notify_suppliers' => true,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.extension.new_due_at', $newDue->toIso8601String());

    $rfq->refresh();

    expect($rfq->due_at?->equalTo($newDue))->toBeTrue()
        ->and(RfqDeadlineExtension::count())->toBe(1);

    $extension = RfqDeadlineExtension::first();

    expect($extension)
        ->not->toBeNull()
        ->and($extension?->reason)->toContain('Design scope');

    expect(Notification::query()->where('event_type', 'rfq.deadline.extended')->count())->toBeGreaterThanOrEqual(1);
});

it('rejects deadline extensions that are not later than the current due date', function (): void {
    $plan = Plan::factory()->create([
        'code' => 'community',
        'price_usd' => 0,
    ]);

    $buyerCompany = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $buyerUser = User::factory()->create([
        'company_id' => $buyerCompany->id,
        'role' => 'buyer_admin',
    ]);

    DB::table('company_user')->insert([
        'company_id' => $buyerCompany->id,
        'user_id' => $buyerUser->id,
        'role' => $buyerUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rfq = RFQ::factory()->for($buyerCompany)->create([
        'status' => RFQ::STATUS_OPEN,
        'due_at' => now()->addDays(3),
    ]);

    actingAs($buyerUser);

    $response = $this->postJson("/api/rfqs/{$rfq->id}/extend-deadline", [
        'new_due_at' => now()->addDay()->toIso8601String(),
        'reason' => 'Need more time',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.new_due_at.0', fn ($value) => str_contains($value, 'later'));
});

it('prevents deadline extensions once the rfq is closed', function (): void {
    $plan = Plan::factory()->create([
        'code' => 'community',
        'price_usd' => 0,
    ]);

    $buyerCompany = Company::factory()->create([
        'plan_id' => $plan->id,
        'plan_code' => $plan->code,
    ]);

    $buyerUser = User::factory()->create([
        'company_id' => $buyerCompany->id,
        'role' => 'buyer_admin',
    ]);

    DB::table('company_user')->insert([
        'company_id' => $buyerCompany->id,
        'user_id' => $buyerUser->id,
        'role' => $buyerUser->role,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $rfq = RFQ::factory()->for($buyerCompany)->create([
        'status' => RFQ::STATUS_CLOSED,
        'due_at' => now()->addDays(1),
    ]);

    actingAs($buyerUser);

    $response = $this->postJson("/api/rfqs/{$rfq->id}/extend-deadline", [
        'new_due_at' => now()->addDays(4)->toIso8601String(),
        'reason' => 'Need more time',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.rfq.0', fn ($value) => str_contains($value, 'only be extended while the RFQ is open'));
});
