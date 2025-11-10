<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Analytics\GenerateAnalyticsRequest;
use App\Http\Resources\AnalyticsOverviewResource;
use App\Http\Resources\AnalyticsSnapshotResource;
use App\Models\AnalyticsSnapshot;
use App\Models\Company;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends ApiController
{
    public function __construct(private readonly AnalyticsService $analyticsService)
    {
    }

    public function overview(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $user->loadMissing('company.plan');
        $company = $user->company;

        if (! $company instanceof Company) {
            return $this->fail('Company context required.', 403);
        }

        $plan = $company->plan;

        if ($plan === null || ! $plan->analytics_enabled) {
            return $this->fail('Analytics not available on current plan.', 403);
        }

        $historyLimit = max(0, (int) $plan->analytics_history_months);

        if ($historyLimit === 0) {
            return $this->ok([], 'No analytics history available for this plan.');
        }

        $earliest = Carbon::now()->startOfMonth()->subMonths($historyLimit - 1);
        $defaultMonths = max(1, min(6, $historyLimit));
        $defaultFrom = Carbon::now()->startOfMonth()->subMonths($defaultMonths - 1);
        $defaultTo = Carbon::now()->endOfMonth();

        $from = $request->query('from') ? Carbon::parse($request->query('from'))->startOfMonth() : $defaultFrom;
        $to = $request->query('to') ? Carbon::parse($request->query('to'))->endOfMonth() : $defaultTo;

        if ($from->lessThan($earliest)) {
            $from = $earliest;
        }

        if ($to->greaterThan(Carbon::now()->endOfMonth())) {
            $to = Carbon::now()->endOfMonth();
        }

        if ($from->greaterThan($to)) {
            $from = $to->copy()->startOfMonth();
        }

        $snapshots = AnalyticsSnapshot::query()
            ->where('company_id', $company->id)
            ->whereBetween('period_start', [$from->toDateString(), $to->toDateString()])
            ->orderBy('period_start')
            ->get()
            ->groupBy('type')
            ->map(fn ($group) => $group->sortBy('period_start')->values());

        return $this->ok(
            (new AnalyticsOverviewResource($snapshots))->toArray($request),
            'Analytics overview retrieved.',
            [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ]
        );
    }

    public function generate(GenerateAnalyticsRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $user->loadMissing('company.plan');
        $company = $user->company;

        if (! $company instanceof Company) {
            return $this->fail('Company context required.', 403);
        }

        $plan = $company->plan;

        if ($plan === null || ! $plan->analytics_enabled) {
            return $this->fail('Analytics not available on current plan.', 403);
        }

        $period = $request->period();
        $start = $period['start'];
        $end = $period['end'];

        $historyLimit = max(0, (int) $plan->analytics_history_months);

        $snapshotExists = AnalyticsSnapshot::query()
            ->where('company_id', $company->id)
            ->where('period_start', $start->toDateString())
            ->where('period_end', $end->toDateString())
            ->exists();

        if (! $snapshotExists && $historyLimit > 0 && $company->analytics_usage_months >= $historyLimit) {
            return $this->fail('Upgrade required.', 402);
        }

        $results = $this->analyticsService->generateForPeriod($company, $start, $end);

        return $this->ok(
            AnalyticsSnapshotResource::collection($results->values())->toArray($request),
            'Analytics generated.',
            [
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
            ]
        );
    }
}
