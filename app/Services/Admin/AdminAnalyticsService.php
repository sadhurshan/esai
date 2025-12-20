<?php

namespace App\Services\Admin;

use App\Enums\CompanyStatus;
use App\Enums\SupplierApplicationStatus;
use App\Models\AiWorkflow;
use App\Models\AiWorkflowStep;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RFQ;
use App\Models\Subscription;
use App\Models\SupplierApplication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsService
{
    /**
     * Build a summarized snapshot for the platform admin dashboard.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $monthStart->copy()->subMonth();
        $lastMonthEnd = $monthStart->copy()->subDay();
        $trendStart = $now->copy()->subMonths(5)->startOfMonth();

        $tenantsTotal = Company::count();
        $activeTenants = Company::where('status', CompanyStatus::Active->value)->count();
        $trialTenants = Company::where('status', CompanyStatus::Trial->value)->count();
        $suspendedTenants = Company::where('status', CompanyStatus::Suspended->value)->count();
        $pendingTenants = Company::whereIn('status', [
            CompanyStatus::Pending->value,
            CompanyStatus::PendingVerification->value,
        ])->count();

        $rfqsThisMonth = RFQ::whereBetween('created_at', [$monthStart, $now])->count();
        $rfqsLastMonth = RFQ::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();

        $quotesThisMonth = Quote::whereBetween('created_at', [$monthStart, $now])->count();
        $purchaseOrdersThisMonth = PurchaseOrder::whereBetween('created_at', [$monthStart, $now])->count();

        $totalStorageUsed = (float) Company::sum('storage_used_mb');
        $averageStorageUsed = $tenantsTotal > 0 ? round($totalStorageUsed / $tenantsTotal, 2) : 0.0;
        $rfqsCapacityUsed = (int) Company::sum('rfqs_monthly_used');

        $totalUsers = User::count();
        $activeUsersWeek = User::whereNotNull('last_login_at')
            ->where('last_login_at', '>=', $now->copy()->subDays(7))
            ->count();

        $pendingSupplierApplications = SupplierApplication::where('status', SupplierApplicationStatus::Pending->value)->count();
        $listedSuppliers = Company::listedSuppliers()->count();

        $activeSubscriptions = Subscription::where('stripe_status', 'active')->count();
        $trialSubscriptions = Subscription::where('stripe_status', 'trialing')->count();
        $pastDueSubscriptions = Subscription::where('stripe_status', 'past_due')->count();

        $recentCompanies = Company::query()
            ->with('plan')
            ->latest()
            ->limit(5)
            ->get();

        $recentAuditLogs = AuditLog::query()
            ->with('user:id,name,email')
            ->latest()
            ->limit(5)
            ->get();

        $workflowMetrics = $this->workflowMetrics($now);

        return [
            'tenants' => [
                'total' => $tenantsTotal,
                'active' => $activeTenants,
                'trialing' => $trialTenants,
                'suspended' => $suspendedTenants,
                'pending' => $pendingTenants,
            ],
            'subscriptions' => [
                'active' => $activeSubscriptions,
                'trialing' => $trialSubscriptions,
                'past_due' => $pastDueSubscriptions,
            ],
            'usage' => [
                'rfqs_month_to_date' => $rfqsThisMonth,
                'rfqs_last_month' => $rfqsLastMonth,
                'rfqs_capacity_used' => $rfqsCapacityUsed,
                'quotes_month_to_date' => $quotesThisMonth,
                'purchase_orders_month_to_date' => $purchaseOrdersThisMonth,
                'storage_used_mb' => (int) round($totalStorageUsed),
                'avg_storage_used_mb' => $averageStorageUsed,
            ],
            'people' => [
                'users_total' => $totalUsers,
                'active_last_7_days' => $activeUsersWeek,
                'listed_suppliers' => $listedSuppliers,
            ],
            'approvals' => [
                'pending_companies' => $pendingTenants,
                'pending_supplier_applications' => $pendingSupplierApplications,
            ],
            'trends' => [
                'rfqs' => $this->monthlyCounts('rfqs', $trendStart, $now),
                'tenants' => $this->monthlyCounts('companies', $trendStart, $now),
            ],
            'workflows' => $workflowMetrics,
            'recent_companies' => $recentCompanies,
            'recent_audit_logs' => $recentAuditLogs,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowMetrics(Carbon $now): array
    {
        $windowDays = 7;
        $windowStart = $now->copy()->subDays($windowDays);

        $baseQuery = AiWorkflow::query()->whereBetween('created_at', [$windowStart, $now]);

        $total = (clone $baseQuery)->count();
        $completed = (clone $baseQuery)->where('status', AiWorkflow::STATUS_COMPLETED)->count();
        $inProgress = (clone $baseQuery)
            ->whereIn('status', [AiWorkflow::STATUS_PENDING, AiWorkflow::STATUS_IN_PROGRESS])
            ->count();
        $failedStatuses = [
            AiWorkflow::STATUS_FAILED,
            AiWorkflow::STATUS_REJECTED,
            AiWorkflow::STATUS_ABORTED,
        ];
        $failed = (clone $baseQuery)->whereIn('status', $failedStatuses)->count();

        $completionRate = $total > 0 ? round(($completed / $total) * 100, 2) : 0.0;

        return [
            'window_days' => $windowDays,
            'total_started' => $total,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'failed' => $failed,
            'completion_rate' => $completionRate,
            'avg_step_approval_minutes' => $this->averageApprovalMinutes($windowStart, $now),
            'failed_alerts' => $this->failedWorkflowAlerts(),
        ];
    }

    private function averageApprovalMinutes(Carbon $start, Carbon $end): ?float
    {
        $durations = AiWorkflowStep::query()
            ->whereNotNull('approved_at')
            ->whereBetween('approved_at', [$start, $end])
            ->select(['created_at', 'approved_at'])
            ->get()
            ->map(function (AiWorkflowStep $step): ?int {
                if ($step->approved_at === null || $step->created_at === null) {
                    return null;
                }

                $seconds = $step->approved_at->diffInSeconds($step->created_at, false);

                return abs($seconds);
            })
            ->filter(fn (?int $seconds): bool => $seconds !== null);

        if ($durations->isEmpty()) {
            return null;
        }

        return round(($durations->avg() ?? 0) / 60, 1);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function failedWorkflowAlerts(): array
    {
        return AiWorkflow::query()
            ->with(['owner:id,name,email', 'company:id,name'])
            ->whereIn('status', [
                AiWorkflow::STATUS_FAILED,
                AiWorkflow::STATUS_REJECTED,
                AiWorkflow::STATUS_ABORTED,
            ])
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(function (AiWorkflow $workflow): array {
                return [
                    'workflow_id' => $workflow->workflow_id,
                    'workflow_type' => $workflow->workflow_type,
                    'status' => $workflow->status,
                    'company' => [
                        'id' => $workflow->company_id,
                        'name' => optional($workflow->company)->name,
                    ],
                    'owner' => $workflow->owner ? [
                        'id' => $workflow->owner->id,
                        'name' => $workflow->owner->name,
                        'email' => $workflow->owner->email,
                    ] : null,
                    'current_step' => $workflow->current_step,
                    'current_step_label' => $workflow->current_step !== null
                        ? $workflow->stepName((int) $workflow->current_step)
                        : null,
                    'last_event_type' => $workflow->last_event_type,
                    'last_event_time' => optional($workflow->last_event_time)->toIso8601String(),
                    'updated_at' => optional($workflow->updated_at)->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * @return list<array{period: string, count: int}>
     */
    private function monthlyCounts(string $table, Carbon $start, Carbon $end): array
    {
        $start = $start->copy()->startOfMonth();
        $end = $end->copy()->endOfMonth();

        $buckets = [];
        $cursor = $start->copy();

        while ($cursor <= $end) {
            $buckets[$cursor->format('Y-m')] = 0;
            $cursor->addMonth();
        }

        $bucketExpression = $this->monthBucketExpression();

        $results = DB::table($table)
            ->selectRaw("{$bucketExpression} as bucket")
            ->selectRaw('COUNT(*) as total')
            ->whereBetween('created_at', [$start, $end])
            ->whereNull('deleted_at')
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        foreach ($results as $row) {
            $monthKey = Carbon::parse($row->bucket)->format('Y-m');
            if (array_key_exists($monthKey, $buckets)) {
                $buckets[$monthKey] = (int) $row->total;
            }
        }

        return collect($buckets)
            ->map(fn (int $count, string $period): array => [
                'period' => $period,
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    private function monthBucketExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m-01', created_at)",
            'pgsql' => "to_char(date_trunc('month', created_at), 'YYYY-MM-01')",
            default => "DATE_FORMAT(created_at, '%Y-%m-01')",
        };
    }
}
