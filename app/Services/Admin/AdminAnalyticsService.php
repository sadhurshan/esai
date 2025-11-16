<?php

namespace App\Services\Admin;

use App\Enums\CompanyStatus;
use App\Enums\SupplierApplicationStatus;
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
            'recent_companies' => $recentCompanies,
            'recent_audit_logs' => $recentAuditLogs,
        ];
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
