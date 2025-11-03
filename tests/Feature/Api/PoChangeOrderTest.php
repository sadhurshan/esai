<?php

use App\Models\Company;
use App\Models\PoChangeOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use Illuminate\Support\Str;

function createPurchaseOrderFixture(): array
{
	$company = Company::factory()->create();
	$buyer = User::factory()->create(['company_id' => $company->id]);

	$purchaseOrder = PurchaseOrder::query()->create([
		'company_id' => $company->id,
		'po_number' => sprintf('PO-%s', Str::upper(Str::random(8))),
		'currency' => 'USD',
		'status' => 'sent',
		'revision_no' => 0,
	]);

	$line = PurchaseOrderLine::query()->create([
		'purchase_order_id' => $purchaseOrder->id,
		'line_no' => 1,
		'description' => 'CNC machined bracket',
		'quantity' => 10,
		'uom' => 'EA',
		'unit_price' => 125.75,
		'delivery_date' => now()->addDays(14)->toDateString(),
	]);

	$supplierUser = User::factory()->create();

	return compact('company', 'buyer', 'purchaseOrder', 'line', 'supplierUser');
}

test('approving a change order updates revision and status', function (): void {
	[
		'buyer' => $buyer,
		'purchaseOrder' => $purchaseOrder,
		'line' => $line,
		'supplierUser' => $supplierUser,
	] = createPurchaseOrderFixture();

	$changeOrder = PoChangeOrder::query()->create([
		'purchase_order_id' => $purchaseOrder->id,
		'proposed_by_user_id' => $supplierUser->id,
		'reason' => 'Adjust order quantity for revised demand',
		'status' => 'proposed',
		'changes_json' => [
			'lines' => [
				['id' => $line->id, 'quantity' => 12],
			],
		],
	]);

	$this->actingAs($buyer);

	$response = $this->putJson("/api/change-orders/{$changeOrder->id}/approve");

	$response->assertOk()->assertJsonPath('status', 'success');

	$purchaseOrder->refresh();
	$changeOrder->refresh();
	$line->refresh();

	expect($purchaseOrder->revision_no)->toBe(1);
	expect($changeOrder->status)->toBe('accepted');
	expect($changeOrder->po_revision_no)->toBe(1);
	expect($line->quantity)->toBe(12);
});

test('rejecting a change order marks it rejected without altering revision', function (): void {
	[
		'buyer' => $buyer,
		'purchaseOrder' => $purchaseOrder,
		'supplierUser' => $supplierUser,
	] = createPurchaseOrderFixture();

	$changeOrder = PoChangeOrder::query()->create([
		'purchase_order_id' => $purchaseOrder->id,
		'proposed_by_user_id' => $supplierUser->id,
		'reason' => 'Update delivery schedule',
		'status' => 'proposed',
		'changes_json' => [
			'purchase_order' => [
				'incoterm' => 'FOB',
			],
		],
	]);

	$this->actingAs($buyer);

	$response = $this->putJson("/api/change-orders/{$changeOrder->id}/reject");

	$response->assertOk()->assertJsonPath('status', 'success');

	$purchaseOrder->refresh();
	$changeOrder->refresh();

	expect($purchaseOrder->revision_no)->toBe(0);
	expect($changeOrder->status)->toBe('rejected');
	expect($changeOrder->po_revision_no)->toBeNull();
});
