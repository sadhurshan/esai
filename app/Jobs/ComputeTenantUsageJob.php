<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\UsageSnapshot;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ComputeTenantUsageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private CarbonImmutable $snapshotDate;

    public function __construct(CarbonInterface|string|null $snapshotDate = null)
    {
        if ($snapshotDate instanceof CarbonInterface) {
            $this->snapshotDate = CarbonImmutable::instance($snapshotDate)->startOfDay();
        } elseif (is_string($snapshotDate)) {
            $this->snapshotDate = CarbonImmutable::parse($snapshotDate)->startOfDay();
        } else {
            $this->snapshotDate = CarbonImmutable::yesterday()->startOfDay();
        }
    }

    public function handle(): void
    {
        $start = $this->snapshotDate;
        $end = $this->snapshotDate->endOfDay();

        $rfqCounts = $this->countsFor(RFQ::class, $start, $end);
        $quoteCounts = $this->countsFor(Quote::class, $start, $end);
        $poCounts = $this->countsFor(PurchaseOrder::class, $start, $end);

        Company::query()
            ->select(['id', 'storage_used_mb'])
            ->chunkById(250, function (Collection $companies) use ($start, $rfqCounts, $quoteCounts, $poCounts): void {
                foreach ($companies as $company) {
                    UsageSnapshot::updateOrCreate(
                        [
                            'company_id' => $company->id,
                            'date' => $start,
                        ],
                        [
                            'date' => $start,
                            'rfqs_count' => $rfqCounts[$company->id] ?? 0,
                            'quotes_count' => $quoteCounts[$company->id] ?? 0,
                            'pos_count' => $poCounts[$company->id] ?? 0,
                            'storage_used_mb' => (int) $company->storage_used_mb,
                        ]
                    );
                }
            });
    }

    /**
     * @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass
     * @return array<int, int>
     */
    private function countsFor(string $modelClass, CarbonImmutable $start, CarbonImmutable $end): array
    {
        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $modelClass();

        return $model->newQuery()
            ->select('company_id')
            ->selectRaw('COUNT(*) as aggregate_total')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('company_id')
            ->pluck('aggregate_total', 'company_id')
            ->map(fn ($value): int => (int) $value)
            ->all();
    }
}
