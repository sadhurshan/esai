<?php

namespace App\Jobs;

use App\Enums\InventoryTxnType;
use App\Models\Company;
use App\Models\ForecastSnapshot;
use App\Models\InventoryTxn;
use App\Models\Part;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ComputeInventoryForecastSnapshotsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const DEMAND_LOOKBACK_DAYS = 90;
    private const FORECAST_HORIZON_DAYS = 30;

    /**
     * @var list<InventoryTxnType>
     */
    private const DEMAND_SIGNAL_TYPES = [
        InventoryTxnType::Issue,
        InventoryTxnType::AdjustOut,
        InventoryTxnType::TransferOut,
        InventoryTxnType::ReturnOut,
    ];

    public function handle(): void
    {
        Company::query()
            ->select('id')
            ->chunkById(50, function ($companies): void {
                foreach ($companies as $company) {
                    $this->snapshotCompany((int) $company->id);
                }
            });
    }

    private function snapshotCompany(int $companyId): void
    {
        Part::query()
            ->where('company_id', $companyId)
            ->where(function ($query): void {
                $query->whereHas('inventories')
                    ->orWhereHas('inventorySetting');
            })
            ->with(['inventories', 'inventorySetting'])
            ->chunkById(100, function ($parts) use ($companyId): void {
                foreach ($parts as $part) {
                    $this->snapshotPart($companyId, $part);
                }
            });
    }

    private function snapshotPart(int $companyId, Part $part): void
    {
        $periodStart = now()->startOfDay();
        $periodEnd = $periodStart->copy()->addDays(self::FORECAST_HORIZON_DAYS);

        $inventories = $part->relationLoaded('inventories') ? $part->inventories : collect();
        $onHand = (float) ($inventories?->sum('on_hand') ?? 0.0);
        $onOrder = (float) ($inventories?->sum('on_order') ?? 0.0);

        $inventorySetting = $part->relationLoaded('inventorySetting') ? $part->inventorySetting : null;
        $safetyStock = (float) ($inventorySetting?->safety_stock ?? 0.0);

        $avgDailyDemand = $this->calculateAverageDailyDemand($companyId, $part->id);
        $demandQty = round($avgDailyDemand * self::FORECAST_HORIZON_DAYS, 3);

        $runoutDays = null;
        if ($avgDailyDemand > 0) {
            $bufferQty = max(0.0, $onHand + $onOrder - $safetyStock);
            $runoutDays = round($bufferQty / $avgDailyDemand, 2);
        }

        ForecastSnapshot::updateOrCreate(
            [
                'company_id' => $companyId,
                'part_id' => $part->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'method' => 'ema',
            ],
            [
                'demand_qty' => $demandQty,
                'avg_daily_demand' => $avgDailyDemand,
                'alpha' => 0.5,
                'on_hand_qty' => round($onHand, 3),
                'on_order_qty' => round($onOrder, 3),
                'safety_stock_qty' => round($safetyStock, 3),
                'projected_runout_days' => $runoutDays,
                'horizon_days' => self::FORECAST_HORIZON_DAYS,
            ]
        );
    }

    private function calculateAverageDailyDemand(int $companyId, int $partId): float
    {
        $lookbackStart = now()->copy()->subDays(self::DEMAND_LOOKBACK_DAYS)->startOfDay();
        $types = array_map(static fn (InventoryTxnType $type) => $type->value, self::DEMAND_SIGNAL_TYPES);

        $totalDemand = (float) InventoryTxn::query()
            ->where('company_id', $companyId)
            ->where('part_id', $partId)
            ->whereIn('type', $types)
            ->where('created_at', '>=', $lookbackStart)
            ->sum(DB::raw('ABS(COALESCE(qty, 0))'));

        if ($totalDemand <= 0.0) {
            return 0.0;
        }

        return round($totalDemand / self::DEMAND_LOOKBACK_DAYS, 3);
    }
}
