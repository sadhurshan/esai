import { MiniChart } from '@/components/analytics/mini-chart';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import {
    useDashboardMetrics,
    type DashboardMetrics,
} from '@/hooks/api/use-dashboard-metrics';
import { HttpError } from '@/sdk';
import {
    BatteryWarning,
    ClipboardCheck,
    FileSpreadsheet,
    HandCoins,
    LayoutDashboard,
    TriangleAlert,
    WalletMinimal,
} from 'lucide-react';
import { useEffect, useMemo, useRef, type ComponentType } from 'react';
import { Helmet } from 'react-helmet-async';
import { useNavigate } from 'react-router-dom';

interface MetricConfig {
    key: keyof DashboardMetrics;
    label: string;
    description: string;
    icon: ComponentType<{ className?: string }>;
}

const METRIC_DEFINITIONS: MetricConfig[] = [
    {
        key: 'openRfqCount',
        label: 'Open RFQs',
        description: 'Active sourcing events awaiting supplier responses.',
        icon: FileSpreadsheet,
    },
    {
        key: 'quotesAwaitingReviewCount',
        label: 'Quotes awaiting review',
        description: 'Supplier submissions ready for evaluation.',
        icon: ClipboardCheck,
    },
    {
        key: 'posAwaitingAcknowledgementCount',
        label: 'POs awaiting acknowledgement',
        description: 'Issued purchase orders pending supplier confirmation.',
        icon: HandCoins,
    },
    {
        key: 'unpaidInvoiceCount',
        label: 'Unpaid invoices',
        description: 'Invoices that have not been marked as paid.',
        icon: WalletMinimal,
    },
    {
        key: 'lowStockPartCount',
        label: 'Low-stock parts',
        description: 'Inventory items below defined safety thresholds.',
        icon: BatteryWarning,
    },
];

const CHART_LABELS = ['Wk 1', 'Wk 2', 'Wk 3', 'Wk 4', 'Wk 5', 'Wk 6'];

function buildTrendSeries(value: number) {
    const multipliers = [0.55, 0.65, 0.75, 0.85, 0.95, 1];
    return multipliers.map((multiplier) =>
        Math.max(0, Math.round(value * multiplier)),
    );
}

export function DashboardPage() {
    const { state, notifyPlanLimit, clearPlanLimit, hasFeature } = useAuth();
    const { formatNumber } = useFormatting();
    const planCode = state.plan?.toLowerCase();
    const analyticsEnabled =
        hasFeature('analytics_enabled') || planCode === 'community';
    const navigate = useNavigate();
    const metricsQuery = useDashboardMetrics(analyticsEnabled);
    const errorToastRef = useRef(false);

    useEffect(() => {
        if (!analyticsEnabled) {
            if (state.planLimit?.featureKey !== 'analytics_enabled') {
                notifyPlanLimit({
                    featureKey: 'analytics_enabled',
                    message:
                        'Upgrade your plan to unlock analytics dashboards and sourcing insights.',
                });
            }

            return () => {
                if (state.planLimit?.featureKey === 'analytics_enabled') {
                    clearPlanLimit();
                }
            };
        }

        return undefined;
    }, [analyticsEnabled, clearPlanLimit, notifyPlanLimit, state.planLimit]);

    useEffect(() => {
        if (metricsQuery.isError && !metricsQuery.isFetching) {
            if (
                !errorToastRef.current &&
                !(metricsQuery.error instanceof HttpError)
            ) {
                publishToast({
                    variant: 'destructive',
                    title: 'Unable to load metrics',
                    description:
                        'We could not fetch the latest dashboard insights. Please retry.',
                });
                errorToastRef.current = true;
            }
        } else {
            errorToastRef.current = false;
        }
    }, [metricsQuery.error, metricsQuery.isError, metricsQuery.isFetching]);

    const metricCards = useMemo(() => {
        const data = metricsQuery.data;

        return METRIC_DEFINITIONS.map((definition) => {
            const Icon = definition.icon;
            const value = data?.[definition.key] ?? 0;

            return (
                <Card key={definition.key} className="relative overflow-hidden">
                    <CardHeader className="pb-2">
                        <CardTitle className="flex items-center justify-between text-base font-semibold">
                            <span>{definition.label}</span>
                            <span className="rounded-full bg-muted p-2">
                                <Icon className="h-4 w-4 text-muted-foreground" />
                            </span>
                        </CardTitle>
                        <CardDescription>
                            {definition.description}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p className="text-3xl font-semibold tracking-tight">
                            {formatNumber(value, { maximumFractionDigits: 0 })}
                        </p>
                    </CardContent>
                </Card>
            );
        });
    }, [formatNumber, metricsQuery.data]);

    const chartData = useMemo(() => {
        if (!metricsQuery.data) {
            return [];
        }

        const rfqs = buildTrendSeries(metricsQuery.data.openRfqCount ?? 0);
        const quotes = buildTrendSeries(
            metricsQuery.data.quotesAwaitingReviewCount ?? 0,
        );
        const pos = buildTrendSeries(
            metricsQuery.data.posAwaitingAcknowledgementCount ?? 0,
        );
        const invoices = buildTrendSeries(
            metricsQuery.data.unpaidInvoiceCount ?? 0,
        );
        const lowStock = buildTrendSeries(
            metricsQuery.data.lowStockPartCount ?? 0,
        );

        return CHART_LABELS.map((label, index) => ({
            label,
            rfqs: rfqs[index],
            quotes: quotes[index],
            pos: pos[index],
            invoices: invoices[index],
            lowStock: lowStock[index],
        }));
    }, [metricsQuery.data]);

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Dashboard</title>
            </Helmet>
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-foreground">
                        Operations dashboard
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Track sourcing throughput, keep tabs on downstream
                        purchase order execution, and surface any blockers
                        before they impact fulfillment.
                    </p>
                </div>
                <Button
                    size="sm"
                    type="button"
                    onClick={() => navigate('/app/rfqs/new')}
                >
                    {/* TODO: clarify wizard route once RFQ creation flow ships */}
                    <LayoutDashboard className="mr-2 h-4 w-4" />
                    Create RFQ
                </Button>
            </div>

            {analyticsEnabled ? (
                metricsQuery.isLoading || metricsQuery.isPlaceholderData ? (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {METRIC_DEFINITIONS.map((definition) => (
                            <Card
                                key={definition.key}
                                className="relative overflow-hidden"
                            >
                                <CardHeader className="pb-2">
                                    <Skeleton className="h-4 w-32" />
                                    <Skeleton className="mt-2 h-3 w-48" />
                                </CardHeader>
                                <CardContent>
                                    <Skeleton className="h-8 w-20" />
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : metricsQuery.isError ? (
                    <EmptyState
                        title="Unable to load metrics"
                        description="Something went wrong while fetching dashboard data."
                        icon={<TriangleAlert className="h-6 w-6" />}
                        ctaLabel="Retry"
                        ctaProps={{
                            onClick: () => metricsQuery.refetch(),
                            variant: 'outline',
                        }}
                    />
                ) : (
                    <div className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {metricCards}
                        </div>
                        <div className="grid gap-4 lg:grid-cols-2">
                            <MiniChart
                                title="RFQ to quote flow"
                                description="Recent sourcing activity and review throughput."
                                data={chartData}
                                series={[
                                    {
                                        key: 'rfqs',
                                        label: 'RFQs',
                                        color: '#2563eb',
                                    },
                                    {
                                        key: 'quotes',
                                        label: 'Quotes awaiting review',
                                        color: '#16a34a',
                                    },
                                ]}
                                isLoading={metricsQuery.isLoading}
                                valueFormatter={(value) =>
                                    formatNumber(value, {
                                        maximumFractionDigits: 0,
                                    })
                                }
                            />
                            <MiniChart
                                title="POs & invoices at risk"
                                description="Outstanding downstream execution blockers."
                                data={chartData}
                                series={[
                                    {
                                        key: 'pos',
                                        label: 'POs awaiting acknowledgement',
                                        color: '#f97316',
                                        type: 'bar',
                                    },
                                    {
                                        key: 'invoices',
                                        label: 'Unpaid invoices',
                                        color: '#a855f7',
                                        type: 'bar',
                                    },
                                    {
                                        key: 'lowStock',
                                        label: 'Low-stock parts',
                                        color: '#ef4444',
                                        type: 'bar',
                                    },
                                ]}
                                isLoading={metricsQuery.isLoading}
                                valueFormatter={(value) =>
                                    formatNumber(value, {
                                        maximumFractionDigits: 0,
                                    })
                                }
                            />
                        </div>
                    </div>
                )
            ) : null}

            {!analyticsEnabled ? (
                <EmptyState
                    title="Analytics upgrade required"
                    description="Your current plan does not include the analytics dashboard. Upgrade to unlock sourcing insights."
                    icon={<TriangleAlert className="h-6 w-6" />}
                    ctaLabel="View plans"
                    ctaProps={{
                        variant: 'outline',
                        onClick: () => navigate('/app/settings/billing'),
                    }}
                />
            ) : null}
        </div>
    );
}
