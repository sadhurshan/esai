<?php

use App\Enums\CompanySupplierStatus;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\RfqInvitation;
use App\Models\Supplier;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Testing\Fluent\AssertableJson;

use function Pest\Laravel\actingAs;

it('returns aggregated supplier dashboard metrics for the active supplier persona', function (): void {
	$buyer = createSubscribedCompany();
	$supplierContext = createSupplierPersonaForBuyer($buyer);

	CompanyContext::forCompany($buyer->id, function () use ($buyer, $supplierContext): void {
		$rfq = RFQ::factory()->create([
			'company_id' => $buyer->id,
			'status' => 'open',
		]);

		RfqInvitation::factory()->create([
			'company_id' => $buyer->id,
			'rfq_id' => $rfq->id,
			'supplier_id' => $supplierContext['supplier']->id,
			'status' => RfqInvitation::STATUS_PENDING,
		]);

		$otherSupplier = Supplier::factory()->create([
			'status' => 'approved',
		]);

		RfqInvitation::factory()->create([
			'company_id' => $buyer->id,
			'rfq_id' => $rfq->id,
			'supplier_id' => $otherSupplier->id,
			'status' => RfqInvitation::STATUS_PENDING,
		]);

		Quote::factory()->create([
			'company_id' => $buyer->id,
			'rfq_id' => $rfq->id,
			'supplier_id' => $supplierContext['supplier']->id,
			'status' => 'draft',
			'submitted_by' => null,
			'submitted_at' => null,
		]);

		Quote::factory()->create([
			'company_id' => $buyer->id,
			'rfq_id' => $rfq->id,
			'supplier_id' => $supplierContext['supplier']->id,
			'status' => 'submitted',
			'submitted_by' => $supplierContext['user']->id,
			'revision_no' => 2,
		]);

		Quote::factory()->create([
			'company_id' => $buyer->id,
			'rfq_id' => $rfq->id,
			'supplier_id' => $otherSupplier->id,
			'status' => 'submitted',
		]);

		$pendingPurchaseOrder = PurchaseOrder::factory()->create([
			'company_id' => $buyer->id,
			'rfq_id' => $rfq->id,
			'supplier_id' => $supplierContext['supplier']->id,
			'status' => 'sent',
			'ack_status' => 'sent',
		]);

		$acknowledgedPurchaseOrder = PurchaseOrder::factory()->create([
			'company_id' => $buyer->id,
			'rfq_id' => $rfq->id,
			'supplier_id' => $supplierContext['supplier']->id,
			'status' => 'sent',
			'ack_status' => 'acknowledged',
		]);

		$otherSupplierPurchaseOrder = PurchaseOrder::factory()->create([
			'company_id' => $buyer->id,
			'rfq_id' => $rfq->id,
			'supplier_id' => $otherSupplier->id,
			'status' => 'sent',
			'ack_status' => 'sent',
		]);

		Invoice::factory()->create([
			'company_id' => $buyer->id,
			'purchase_order_id' => $pendingPurchaseOrder->id,
			'supplier_id' => $supplierContext['supplier']->id,
			'status' => 'pending',
		]);

		Invoice::factory()->create([
			'company_id' => $buyer->id,
			'purchase_order_id' => $acknowledgedPurchaseOrder->id,
			'supplier_id' => $supplierContext['supplier']->id,
			'status' => 'paid',
		]);

		Invoice::factory()->create([
			'company_id' => $buyer->id,
			'purchase_order_id' => $otherSupplierPurchaseOrder->id,
			'supplier_id' => $otherSupplier->id,
			'status' => 'pending',
		]);
	});

	actingAs($supplierContext['user']);

	$this
		->withHeaders(['X-Active-Persona' => $supplierContext['persona']['key']])
		->getJson('/api/supplier/dashboard/metrics')
		->assertOk()
		->assertJson(fn (AssertableJson $json) => $json
			->where('status', 'success')
			->where('data.rfq_invitation_count', 1)
			->where('data.quotes_draft_count', 1)
			->where('data.quotes_submitted_count', 1)
			->where('data.purchase_orders_pending_ack_count', 1)
			->where('data.invoices_unpaid_count', 1)
			->etc()
		);
});

it('rejects supplier dashboard metrics when no supplier persona is active', function (): void {
	$company = createSubscribedCompany([
		'supplier_status' => CompanySupplierStatus::Approved->value,
	]);

	$user = User::factory()->create([
		'company_id' => $company->id,
	]);

	actingAs($user);

	$this
		->getJson('/api/supplier/dashboard/metrics')
		->assertStatus(403)
		->assertJson([
			'status' => 'error',
			'message' => 'Supplier persona required.',
			'errors' => [
				'code' => 'supplier_persona_required',
			],
		]);
});
