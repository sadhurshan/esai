<?php

use App\Jobs\ComputeTenantUsageJob;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\Supplier;
use App\Models\UsageSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates or updates usage snapshots for each tenant on the target date', function (): void {
    $company = Company::factory()->create(['storage_used_mb' => 256]);
    $otherCompany = Company::factory()->create(['storage_used_mb' => 128]);

    $targetDate = CarbonImmutable::parse('2025-12-05');

    $rfqA = RFQ::factory()->for($company)->create(['created_at' => $targetDate->setHour(9)]);
    RFQ::factory()->for($company)->create(['created_at' => $targetDate->setHour(10)]);
    RFQ::factory()->for($company)->create(['created_at' => $targetDate->copy()->subDay()]);

    $supplier = Supplier::factory()->create(['company_id' => $company->id]);

    $recentQuote = Quote::factory()->create([
        'company_id' => $company->id,
        'rfq_id' => $rfqA->id,
        'supplier_id' => $supplier->id,
        'unit_price' => 125,
        'lead_time_days' => 14,
    ]);

    $recentQuote->forceFill([
        'created_at' => $targetDate->setHour(12),
        'updated_at' => $targetDate->setHour(12),
    ])->save();

    expect($recentQuote->fresh()->created_at->toDateString())->toBe($targetDate->toDateString());

    $olderQuote = Quote::factory()->create([
        'company_id' => $company->id,
        'rfq_id' => $rfqA->id,
        'supplier_id' => $supplier->id,
        'unit_price' => 180,
        'lead_time_days' => 10,
        'revision_no' => 2,
    ]);

    $olderQuote->forceFill([
        'created_at' => $targetDate->copy()->subDays(2),
        'updated_at' => $targetDate->copy()->subDays(2),
    ])->save();

    PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'created_at' => $targetDate->setHour(14),
    ]);
    PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'created_at' => $targetDate->copy()->subDay(),
    ]);

    $quotesDuringTargetDate = Quote::query()
        ->where('company_id', $company->id)
        ->whereBetween('created_at', [$targetDate->copy()->startOfDay(), $targetDate->copy()->endOfDay()])
        ->count();
    expect($quotesDuringTargetDate)->toBe(1);

    $quoteCountMap = Quote::query()
        ->select('company_id')
        ->selectRaw('COUNT(*) as aggregate_total')
        ->whereBetween('created_at', [$targetDate->copy()->startOfDay(), $targetDate->copy()->endOfDay()])
        ->groupBy('company_id')
        ->pluck('aggregate_total', 'company_id')
        ->toArray();
    expect($quoteCountMap[$company->id] ?? null)->toBe(1);

    $otherRfq = RFQ::factory()->for($otherCompany)->create(['created_at' => $targetDate->setHour(11)]);
    $otherSupplier = Supplier::factory()->create(['company_id' => $otherCompany->id]);

    $otherQuote = Quote::factory()->create([
        'company_id' => $otherCompany->id,
        'rfq_id' => $otherRfq->id,
        'supplier_id' => $otherSupplier->id,
        'unit_price' => 210,
        'lead_time_days' => 9,
    ]);

    $otherQuote->forceFill([
        'created_at' => $targetDate->setHour(8),
        'updated_at' => $targetDate->setHour(8),
    ])->save();

    PurchaseOrder::factory()->count(2)->create([
        'company_id' => $otherCompany->id,
        'created_at' => $targetDate->setHour(16),
    ]);

    UsageSnapshot::factory()->create([
        'company_id' => $otherCompany->id,
        'date' => $targetDate->toDateString(),
        'rfqs_count' => 99,
        'quotes_count' => 99,
        'pos_count' => 99,
        'storage_used_mb' => 10,
    ]);

    (new ComputeTenantUsageJob($targetDate))->handle();

    $primarySnapshot = UsageSnapshot::where('company_id', $company->id)
        ->whereDate('date', $targetDate)
        ->first();

    expect($primarySnapshot)->not->toBeNull();
    expect($primarySnapshot->rfqs_count)->toBe(2);
    expect($primarySnapshot->quotes_count)->toBe(1);
    expect($primarySnapshot->pos_count)->toBe(1);
    expect($primarySnapshot->storage_used_mb)->toBe(256);

    $secondarySnapshot = UsageSnapshot::where('company_id', $otherCompany->id)
        ->whereDate('date', $targetDate)
        ->first();

    expect($secondarySnapshot)->not->toBeNull();
    expect($secondarySnapshot->rfqs_count)->toBe(1);
    expect($secondarySnapshot->quotes_count)->toBe(1);
    expect($secondarySnapshot->pos_count)->toBe(2);
    expect($secondarySnapshot->storage_used_mb)->toBe(128);
});
