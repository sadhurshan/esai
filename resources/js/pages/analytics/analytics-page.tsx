import {
    CircleCheck,
    FileSpreadsheet,
    Inbox,
    Timer,
    TriangleAlert,
    WalletMinimal,
} from 'lucide-react';
import { useEffect, useMemo, type ComponentType } from 'react';
import { Helmet } from 'react-helmet-async';
import { useNavigate } from 'react-router-dom';

import { KpiCard } from '@/components/analytics/kpi-card';
import { MiniChart } from '@/components/analytics/mini-chart';
import { EmptyState } from '@/components/empty-state';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useAuth } from '@/contexts/auth-context';
import {
    useFormatting,
    type FormattingContextValue,
} from '@/contexts/formatting-context';
import {
    useAnalyticsOverview,
    type AnalyticsKpis,
} from '@/hooks/api/analytics/use-analytics';
import { HttpError } from '@/sdk';

type FormatNumberFn = FormattingContextValue['formatNumber'];
type FormatMoneyFn = FormattingContextValue['formatMoney'];

interface KpiDefinition {
    key: keyof AnalyticsKpis;
    label: string;
    description: string;
    icon: ComponentType<{ className?: string }>;
    format: (
        value: number,
        helpers: { formatNumber: FormatNumberFn; formatMoney: FormatMoneyFn },
    ) => string;
}

const KPI_DEFINITIONS: ReadonlyArray<KpiDefinition> = [
    {
        key: 'openRfqs',
        label: 'Open RFQs',
        description: 'Active sourcing packages inside the reporting window.',
        icon: FileSpreadsheet,
        format: (value, { formatNumber }) =>
            formatNumber(value, { maximumFractionDigits: 0, fallback: '—' }),
    },
    {
        key: 'avgCycleTimeDays',
        label: 'Avg RFQ cycle time',
        description: 'Days from publish to first PO issued.',
        icon: Timer,
        format: (value, { formatNumber }) =>
            `${formatNumber(value, { maximumFractionDigits: 1, fallback: '—' })}d`,
    },
    {
        key: 'quotesReceived',
        label: 'Quotes received (30d)',
        description: 'Supplier submissions captured over the last 30 days.',
        icon: Inbox,
        format: (value, { formatNumber }) =>
            formatNumber(value, { maximumFractionDigits: 0, fallback: '—' }),
    },
    {
        key: 'spendTotal',
        label: 'Spend (30d)',
        description: 'Invoice totals booked in the last 30 days.',
        icon: WalletMinimal,
        format: (value, { formatMoney }) =>
            formatMoney(value, { maximumFractionDigits: 0, fallback: '—' }),
    },
    {
        key: 'onTimeReceiptsPct',
        label: 'On-time receipts %',
        description: 'Share of PO lines received by the promised date.',
        icon: CircleCheck,
        format: (value, { formatNumber }) =>
            `${formatNumber(value, { maximumFractionDigits: 1, fallback: '—' })}%`,
    },
];

export function AnalyticsPage() {
    const { state, hasFeature, notifyPlanLimit, clearPlanLimit } = useAuth();
    const navigate = useNavigate();
    const { formatNumber, formatMoney, formatDate } = useFormatting();
    const planCode = state.plan?.toLowerCase();
    const analyticsEnabled =
        hasFeature('analytics_enabled') || planCode === 'community';
    const analyticsQuery = useAnalyticsOverview(analyticsEnabled);
    const isLoading =
        analyticsQuery.isLoading || analyticsQuery.isPlaceholderData;

    useEffect(() => {
        if (!analyticsEnabled) {
            notifyPlanLimit({
                featureKey: 'analytics_enabled',
                message:
                    'Upgrade your plan to unlock sourcing analytics and charting widgets.',
            });

            return () => {
                clearPlanLimit();
            };
        }

        return undefined;
    }, [analyticsEnabled, clearPlanLimit, notifyPlanLimit]);

    const kpiCards = useMemo(() => {
        const kpis = analyticsQuery.data?.kpis;
        const helpers: {
            formatNumber: FormatNumberFn;
            formatMoney: FormatMoneyFn;
        } = { formatNumber, formatMoney };

        return KPI_DEFINITIONS.map((definition) => {
            const Icon = definition.icon;
            const rawValue = kpis?.[definition.key] ?? 0;
            const value = definition.format(rawValue, helpers);

            return (
                <KpiCard
                    key={definition.key}
                    label={definition.label}
                    description={definition.description}
                    value={value}
                    icon={Icon}
                    loading={isLoading}
                />
            );
        });
    }, [analyticsQuery.data?.kpis, formatMoney, formatNumber, isLoading]);

    const rfqTrendData = useMemo(() => {
        return (analyticsQuery.data?.charts.rfqsOverTime ?? []).map(
            (point) => ({
                label: formatPeriodLabel(
                    point.periodStart,
                    point.periodEnd,
                    formatDate,
                ),
                rfqs: point.rfqs,
                quotes: point.quotes,
            }),
        );
    }, [analyticsQuery.data?.charts.rfqsOverTime, formatDate]);

    const spendBySupplierData = useMemo(() => {
        return (analyticsQuery.data?.charts.spendBySupplier ?? []).map(
            (point) => ({
                label: point.supplierName,
                spend: point.total,
            }),
        );
    }, [analyticsQuery.data?.charts.spendBySupplier]);

    const receiptsPerformanceData = useMemo(() => {
        return (analyticsQuery.data?.charts.receiptsPerformance ?? []).map(
            (point) => ({
                label: formatPeriodLabel(
                    point.periodStart,
                    point.periodEnd,
                    formatDate,
                ),
                onTime: point.onTime,
                late: point.late,
            }),
        );
    }, [analyticsQuery.data?.charts.receiptsPerformance, formatDate]);

    const reportRangeLabel = useMemo(() => {
        const meta = analyticsQuery.data?.meta;
        if (!meta?.from || !meta?.to) {
            return 'Current plan window';
        }

        const fromLabel = formatDate(meta.from, {
            month: 'short',
            year: 'numeric',
        });
        const toLabel = formatDate(meta.to, {
            month: 'short',
            year: 'numeric',
        });

        return `${fromLabel} – ${toLabel}`;
    }, [analyticsQuery.data?.meta, formatDate]);

    const errorDescription = analyticsQuery.isError
        ? analyticsQuery.error instanceof HttpError
            ? analyticsQuery.error.message
            : 'We could not fetch analytics data. Please retry.'
        : null;

    if (!analyticsEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Analytics</title>
                </Helmet>
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-foreground">
                        Analytics
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Benchmark sourcing throughput, downstream execution, and
                        supplier performance once analytics is enabled for your
                        plan.
                    </p>
                </div>
                <PlanUpgradeBanner />
                <EmptyState
                    icon={<TriangleAlert className="h-6 w-6" />}
                    title="Analytics upgrade required"
                    description="Your current plan does not include the analytics workspace. Upgrade to see RFQ KPIs and execution charts."
                    ctaLabel="View plans"
                    ctaProps={{
                        onClick: () => navigate('/app/settings/billing'),
                        variant: 'outline',
                    }}
                />
            </div>
        );
    }

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Analytics</title>
            </Helmet>
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-foreground">
                        Analytics overview
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Monitor end-to-end sourcing throughput, supplier
                        responsiveness, and downstream receiving health across
                        the selected reporting window.
                    </p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => analyticsQuery.refetch()}
                    disabled={analyticsQuery.isFetching}
                >
                    Refresh data
                </Button>
            </div>

            <PlanUpgradeBanner />

            <Alert className="bg-muted/50">
                <AlertTitle>Reporting window</AlertTitle>
                <AlertDescription>
                    Showing analytics for{' '}
                    <span className="font-medium text-foreground">
                        {reportRangeLabel}
                    </span>
                    . Data refreshes when new snapshots are generated.
                </AlertDescription>
            </Alert>

            {analyticsQuery.isError ? (
                <EmptyState
                    icon={<TriangleAlert className="h-6 w-6" />}
                    title="Unable to load analytics"
                    description={
                        errorDescription ??
                        'Something went wrong while fetching analytics snapshots.'
                    }
                    ctaLabel="Retry"
                    ctaProps={{ onClick: () => analyticsQuery.refetch() }}
                />
            ) : (
                <>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {kpiCards}
                    </div>

                    <div className="grid gap-4 lg:grid-cols-2">
                        <MiniChart
                            title="RFQs & quotes over time"
                            description="Track sourcing demand alongside supplier engagement."
                            data={rfqTrendData}
                            series={[
                                {
                                    key: 'rfqs',
                                    label: 'RFQs',
                                    color: '#2563eb',
                                },
                                {
                                    key: 'quotes',
                                    label: 'Quotes',
                                    color: '#16a34a',
                                },
                            ]}
                            valueFormatter={(value) =>
                                formatNumber(value, {
                                    maximumFractionDigits: 0,
                                })
                            }
                            isLoading={isLoading}
                        />
                        <MiniChart
                            title="Spend by supplier"
                            description="Top suppliers ranked by invoice totals in the current window."
                            data={spendBySupplierData}
                            series={[
                                {
                                    key: 'spend',
                                    label: 'Spend',
                                    type: 'bar',
                                    color: '#f97316',
                                },
                            ]}
                            valueFormatter={(value) =>
                                formatMoney(value, { maximumFractionDigits: 0 })
                            }
                            isLoading={isLoading}
                        />
                        <div className="lg:col-span-2">
                            <MiniChart
                                title="On-time vs late receipts"
                                description="PO line receipts grouped by promised delivery date."
                                data={receiptsPerformanceData}
                                series={[
                                    {
                                        key: 'onTime',
                                        label: 'On time',
                                        type: 'bar',
                                        color: '#16a34a',
                                        stackId: 'receipts',
                                    },
                                    {
                                        key: 'late',
                                        label: 'Late',
                                        type: 'bar',
                                        color: '#f97316',
                                        stackId: 'receipts',
                                    },
                                ]}
                                valueFormatter={(value) =>
                                    formatNumber(value, {
                                        maximumFractionDigits: 0,
                                    })
                                }
                                isLoading={isLoading}
                                height={280}
                            />
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}

function formatPeriodLabel(
    periodStart: string | null,
    periodEnd: string | null,
    formatDate: FormattingContextValue['formatDate'],
) {
    if (periodStart) {
        return formatDate(periodStart, { month: 'short', year: 'numeric' });
    }

    if (periodEnd) {
        return formatDate(periodEnd, { month: 'short', year: 'numeric' });
    }

    return '—';
}
