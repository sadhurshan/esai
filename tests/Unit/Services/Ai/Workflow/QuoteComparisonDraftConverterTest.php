<?php

use App\Models\AiWorkflow;
use App\Models\AiWorkflowStep;
use App\Models\Company;
use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RFQ;
use App\Models\RfqItem;
use App\Models\RfqItemAward;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ai\Workflow\QuoteComparisonDraftConverter;
use App\Support\Notifications\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Currency::query()->firstOrCreate([
        'code' => 'USD',
    ], [
        'name' => 'US Dollar',
        'minor_unit' => 2,
        'symbol' => '$',
    ]);

    $notificationMock = Mockery::mock(NotificationService::class);
    $notificationMock->shouldReceive('send')->andReturnNull();
    $this->app->instance(NotificationService::class, $notificationMock);
});

afterEach(function (): void {
    Mockery::close();
});

it('shortlists quotes and awards the recommended supplier when a comparison draft is approved', function (): void {
    Carbon::setTestNow('2025-01-15 12:00:00');

    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $companyId = $company->id;

    $rfq = RFQ::factory()->create([
        'company_id' => $companyId,
        'created_by' => $user->id,
        'status' => RFQ::STATUS_OPEN,
    ]);

    $rfqItems = RfqItem::factory()->count(2)->create([
        'company_id' => $companyId,
        'rfq_id' => $rfq->id,
        'created_by' => $user->id,
    ]);

    $supplierA = Supplier::factory()->create(['company_id' => Company::factory()->create()->id]);
    $supplierB = Supplier::factory()->create(['company_id' => Company::factory()->create()->id]);

    $quoteA = Quote::factory()->create([
        'company_id' => $companyId,
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplierA->id,
        'status' => 'submitted',
    ]);

    $quoteB = Quote::factory()->create([
        'company_id' => $companyId,
        'rfq_id' => $rfq->id,
        'supplier_id' => $supplierB->id,
        'status' => 'submitted',
    ]);

    foreach ($rfqItems as $index => $item) {
        QuoteItem::query()->create([
            'company_id' => $companyId,
            'quote_id' => $quoteA->id,
            'rfq_item_id' => $item->id,
            'unit_price' => 50 + $index,
            'currency' => 'USD',
            'lead_time_days' => 10,
            'status' => 'pending',
        ]);

        QuoteItem::query()->create([
            'company_id' => $companyId,
            'quote_id' => $quoteB->id,
            'rfq_item_id' => $item->id,
            'unit_price' => 60 + $index,
            'currency' => 'USD',
            'lead_time_days' => 12,
            'status' => 'pending',
        ]);
    }

    $workflow = AiWorkflow::factory()->create([
        'company_id' => $companyId,
        'user_id' => $user->id,
    ]);

    /** @var AiWorkflowStep $step */
    $step = AiWorkflowStep::factory()->create([
        'company_id' => $companyId,
        'workflow_id' => $workflow->workflow_id,
        'action_type' => 'compare_quotes',
        'input_json' => ['rfq_id' => (string) $rfq->id],
        'output_json' => [
            'summary' => 'Ranked suppliers.',
            'payload' => [
                'recommendation' => (string) $supplierA->id,
                'rankings' => [
                    [
                        'supplier_id' => (string) $supplierA->id,
                        'score' => 92.5,
                        'normalized_score' => 0.92,
                        'notes' => 'Best pricing',
                    ],
                    [
                        'supplier_id' => (string) $supplierB->id,
                        'score' => 80.0,
                        'normalized_score' => 0.8,
                        'notes' => 'Higher cost',
                    ],
                ],
            ],
        ],
        'approval_status' => AiWorkflowStep::APPROVAL_APPROVED,
        'approved_by' => $user->id,
        'approved_at' => now(),
    ]);

    $converter = app(QuoteComparisonDraftConverter::class);
    $result = $converter->convert($step);

    expect($result['rfq_id'])->toBe($rfq->id)
        ->and($result['created_awards'])->toBe(count($rfqItems))
        ->and($result['awarded_supplier_id'])->toBe($supplierA->id);

    expect($quoteA->fresh()->shortlisted_at)->not()->toBeNull();
    expect($quoteB->fresh()->shortlisted_at)->not()->toBeNull();

    expect(RfqItemAward::query()->where('supplier_id', $supplierA->id)->count())->toBe(count($rfqItems));
    expect(RfqItemAward::query()->where('supplier_id', $supplierB->id)->count())->toBe(0);

    expect(PurchaseOrder::count())->toBe(0);

    expect($quoteA->fresh()->items()->pluck('status')->unique()->all())->toEqual(['awarded']);
    expect($quoteB->fresh()->items()->pluck('status')->unique()->all())->toEqual(['rejected']);

    expect($rfq->fresh()->status)->toBe(RFQ::STATUS_AWARDED);
});
