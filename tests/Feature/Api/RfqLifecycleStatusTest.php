<?php

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RFQ;
use App\Models\RfqItem;
use App\Models\RfqItemAward;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * @return array{company: Company, owner: User}
 */
function createLifecycleReadyCompany(): array
{
	$plan = Plan::firstOrCreate(
		['code' => 'lifecycle'],
		Plan::factory()->make(['code' => 'lifecycle'])->getAttributes()
	);

	$company = Company::factory()->create([
		'status' => CompanyStatus::Active->value,
		'plan_id' => $plan->id,
		'plan_code' => $plan->code,
	]);

	$customer = Customer::factory()->create([
		'company_id' => $company->id,
	]);

	Subscription::factory()->create([
		'company_id' => $company->id,
		'customer_id' => $customer->id,
		'stripe_status' => 'active',
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

	return [
		'company' => $company,
		'owner' => $owner,
	];
}

/**
 * @param  int  $lineCount
 * @return array{
 *     supplier: Supplier,
 *     quote: Quote,
 *     rfqItems: \Illuminate\Support\Collection<int, RfqItem>,
 *     quoteItems: \Illuminate\Support\Collection<int, QuoteItem>
 * }
 */
function seedQuoteWithLines(RFQ $rfq, User $owner, int $lineCount = 2): array
{
	$supplier = Supplier::factory()->create([
		'company_id' => $rfq->company_id,
	]);

	$quote = Quote::query()->create([
		'company_id' => $rfq->company_id,
		'rfq_id' => $rfq->id,
		'supplier_id' => $supplier->id,
		'submitted_by' => $owner->id,
		'currency' => 'USD',
		'unit_price' => '100.00',
		'min_order_qty' => 1,
		'lead_time_days' => 10,
		'notes' => null,
		'status' => 'submitted',
		'revision_no' => 1,
		'subtotal' => '0.00',
		'tax_amount' => '0.00',
		'total_price' => '0.00',
		'subtotal_minor' => 0,
		'tax_amount_minor' => 0,
		'total_price_minor' => 0,
		'submitted_at' => Carbon::now(),
		'attachments_count' => 0,
	]);

	$rfqItems = RfqItem::factory()
		->for($rfq, 'rfq')
		->count($lineCount)
		->create([
			'company_id' => $rfq->company_id,
			'created_by' => $owner->id,
			'qty' => 5,
		]);

	$quoteItems = $rfqItems->map(function (RfqItem $item, int $index) use ($quote, $rfq) {
		$price = 125 + $index;

		return QuoteItem::query()->create([
			'quote_id' => $quote->id,
			'company_id' => $rfq->company_id,
			'rfq_item_id' => $item->id,
			'unit_price' => number_format($price, 2, '.', ''),
			'currency' => 'USD',
			'unit_price_minor' => (int) round($price * 100),
			'lead_time_days' => 7,
			'note' => null,
			'status' => 'pending',
		]);
	});

	return [
		'supplier' => $supplier,
		'quote' => $quote,
		'rfqItems' => $rfqItems,
		'quoteItems' => $quoteItems,
	];
}

it('transitions from draft to open when publishing an rfq', function (): void {
	['company' => $company, 'owner' => $owner] = createLifecycleReadyCompany();

	$rfq = RFQ::factory()->for($company)->create([
		'status' => RFQ::STATUS_DRAFT,
		'created_by' => $owner->id,
	]);

	actingAs($owner);

	$dueAt = Carbon::now()->addDays(5);
	$publishAt = Carbon::now()->addHour();

	$this->postJson("/api/rfqs/{$rfq->id}/publish", [
		'due_at' => $dueAt->toIso8601String(),
		'publish_at' => $publishAt->toIso8601String(),
		'notify_suppliers' => false,
	])
		->assertOk()
		->assertJsonPath('data.status', RFQ::STATUS_OPEN);

	$rfq->refresh();

	expect($rfq->status)->toBe(RFQ::STATUS_OPEN)
		->and($rfq->due_at?->toIso8601String())->toBe($dueAt->toIso8601String())
		->and($rfq->publish_at?->toIso8601String())->toBe($publishAt->toIso8601String());
});

it('transitions from open to closed via the close endpoint', function (): void {
	['company' => $company, 'owner' => $owner] = createLifecycleReadyCompany();

	$rfq = RFQ::factory()->for($company)->create([
		'status' => RFQ::STATUS_OPEN,
		'created_by' => $owner->id,
		'publish_at' => Carbon::now()->subDay(),
		'due_at' => Carbon::now()->addDays(3),
	]);

	actingAs($owner);

	$closedAt = Carbon::now()->addHour();

	$this->postJson("/api/rfqs/{$rfq->id}/close", [
		'reason' => 'Quotes reviewed',
		'closed_at' => $closedAt->toIso8601String(),
	])
		->assertOk()
		->assertJsonPath('data.status', RFQ::STATUS_CLOSED);

	$rfq->refresh();

	expect($rfq->status)->toBe(RFQ::STATUS_CLOSED)
		->and($rfq->close_at?->toIso8601String())->toBe($closedAt->toIso8601String())
		->and(data_get($rfq->meta, 'closure_reason'))->toBe('Quotes reviewed');
});

it('transitions from open to cancelled when cancelling an rfq', function (): void {
	['company' => $company, 'owner' => $owner] = createLifecycleReadyCompany();

	$rfq = RFQ::factory()->for($company)->create([
		'status' => RFQ::STATUS_OPEN,
		'created_by' => $owner->id,
		'publish_at' => Carbon::now()->subHours(2),
		'due_at' => Carbon::now()->addDays(4),
	]);

	actingAs($owner);

	$cancelledAt = Carbon::now()->addHours(2);

	$this->postJson("/api/rfqs/{$rfq->id}/cancel", [
		'reason' => 'Budget reallocated',
		'cancelled_at' => $cancelledAt->toIso8601String(),
	])
		->assertOk()
		->assertJsonPath('data.status', RFQ::STATUS_CANCELLED);

	$rfq->refresh();

	expect($rfq->status)->toBe(RFQ::STATUS_CANCELLED)
		->and($rfq->close_at?->toIso8601String())->toBe($cancelledAt->toIso8601String())
		->and(data_get($rfq->meta, 'cancellation_reason'))->toBe('Budget reallocated');
});

it('handles partial and full awards as rfq lines are awarded', function (): void {
	['company' => $company, 'owner' => $owner] = createLifecycleReadyCompany();

	$rfq = RFQ::factory()->for($company)->create([
		'status' => RFQ::STATUS_OPEN,
		'created_by' => $owner->id,
		'publish_at' => Carbon::now()->subDay(),
		'due_at' => Carbon::now()->addDays(2),
	]);

	$payload = seedQuoteWithLines($rfq, $owner, 2);

	actingAs($owner);

	$firstAward = $payload['quoteItems']->first();
	$secondAward = $payload['quoteItems']->last();

	$this->postJson("/api/rfqs/{$rfq->id}/award-lines", [
		'awards' => [[
			'rfq_item_id' => $firstAward->rfq_item_id,
			'quote_item_id' => $firstAward->id,
		]],
	])
		->assertStatus(201);

	$rfq->refresh();

	expect($rfq->status)->toBe(RFQ::STATUS_OPEN)
		->and($rfq->is_partially_awarded)->toBeTrue()
		->and(RfqItemAward::query()->where('rfq_id', $rfq->id)->count())->toBe(1);

	$this->postJson("/api/rfqs/{$rfq->id}/award-lines", [
		'awards' => [[
			'rfq_item_id' => $secondAward->rfq_item_id,
			'quote_item_id' => $secondAward->id,
		]],
	])
		->assertStatus(201);

	$rfq->refresh();

	expect($rfq->status)->toBe(RFQ::STATUS_AWARDED)
		->and($rfq->is_partially_awarded)->toBeFalse()
		->and(RfqItemAward::query()->where('rfq_id', $rfq->id)->count())->toBe(2);
});

it('rejects closing an rfq unless it is open', function (): void {
	['company' => $company, 'owner' => $owner] = createLifecycleReadyCompany();

	$rfq = RFQ::factory()->for($company)->create([
		'status' => RFQ::STATUS_DRAFT,
		'created_by' => $owner->id,
	]);

	actingAs($owner);

	$this->postJson("/api/rfqs/{$rfq->id}/close", [
		'reason' => 'Need supplier bids first',
	])
		->assertStatus(422)
		->assertJsonPath('errors.status.0', 'Only open RFQs can be closed.');
});