<?php

namespace App\Services\Admin;

use App\Models\AiActionDraft;
use App\Models\AiEvent;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AiUsageMetricsService
{
    private const WINDOW_DAYS = 30;

    /**
     * Build a usage summary for the AI admin dashboard.
     *
     * @return array<string, mixed>
     */
    public function summary(?int $companyId = null): array
    {
        $now = Carbon::now();
        $windowStart = $now->copy()->subDays(self::WINDOW_DAYS);

        return [
            'window_days' => self::WINDOW_DAYS,
            'window_start' => $windowStart->toIso8601String(),
            'window_end' => $now->toIso8601String(),
            'actions' => $this->actionMetrics($companyId, $windowStart, $now),
            'forecasts' => $this->forecastMetrics($companyId, $windowStart, $now),
            'help_requests' => $this->helpMetrics($companyId, $windowStart, $now),
            'tool_errors' => $this->toolErrorMetrics($companyId, $windowStart, $now),
        ];
    }

    /**
     * @return array{planned:int,approved:int,approval_rate:?float}
     */
    private function actionMetrics(?int $companyId, Carbon $start, Carbon $end): array
    {
        $planned = $this->withinCompany($companyId, fn (): int => AiActionDraft::query()
            ->whereBetween('created_at', [$start, $end])
            ->count());

        $approved = $this->withinCompany($companyId, fn (): int => AiActionDraft::query()
            ->where('status', AiActionDraft::STATUS_APPROVED)
            ->whereNotNull('approved_at')
            ->whereBetween('approved_at', [$start, $end])
            ->count());

        $approvalRate = $planned > 0 ? round(($approved / $planned) * 100, 2) : null;

        return [
            'planned' => $planned,
            'approved' => $approved,
            'approval_rate' => $approvalRate,
        ];
    }

    /**
     * @return array{generated:int,errors:int}
     */
    private function forecastMetrics(?int $companyId, Carbon $start, Carbon $end): array
    {
        $success = $this->eventCount($companyId, 'forecast', AiEvent::STATUS_SUCCESS, $start, $end);
        $errors = $this->eventCount($companyId, 'forecast', AiEvent::STATUS_ERROR, $start, $end);

        return [
            'generated' => $success,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{total:int}
     */
    private function helpMetrics(?int $companyId, Carbon $start, Carbon $end): array
    {
        $total = $this->eventCount($companyId, 'workspace_help', AiEvent::STATUS_SUCCESS, $start, $end);

        return ['total' => $total];
    }

    /**
     * @return array{total:int,by_feature:list<array{feature:string|null,count:int}>}
     */
    private function toolErrorMetrics(?int $companyId, Carbon $start, Carbon $end): array
    {
        /** @var Collection<int, array{feature:string|null,total:int}> $rows */
        $rows = $this->withinCompany($companyId, fn (): Collection => AiEvent::query()
            ->select(['feature'])
            ->selectRaw('COUNT(*) as total')
            ->where('status', AiEvent::STATUS_ERROR)
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('feature')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'feature' => $row->feature,
                'total' => (int) $row->total,
            ]));

        $total = (int) $rows->sum('total');

        $byFeature = $rows
            ->map(fn (array $row): array => [
                'feature' => $row['feature'] ?? 'unknown',
                'count' => $row['total'],
            ])
            ->values()
            ->all();

        return [
            'total' => $total,
            'by_feature' => $byFeature,
        ];
    }

    private function eventCount(?int $companyId, string $feature, string $status, Carbon $start, Carbon $end): int
    {
        return $this->withinCompany($companyId, fn (): int => AiEvent::query()
            ->where('feature', $feature)
            ->where('status', $status)
            ->whereBetween('created_at', [$start, $end])
            ->count());
    }

    /**
     * @template TValue
     * @param  callable():TValue  $callback
     * @return TValue
     */
    private function withinCompany(?int $companyId, callable $callback)
    {
        if ($companyId === null) {
            return CompanyContext::bypass($callback);
        }

        return CompanyContext::forCompany($companyId, $callback);
    }
}
