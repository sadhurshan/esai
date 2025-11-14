import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuth } from '@/contexts/auth-context';
import { useDashboardMetrics, type DashboardMetrics } from '@/hooks/api/use-dashboard-metrics';
import { publishToast } from '@/components/ui/use-toast';
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
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate } from 'react-router-dom';
import { useEffect, useMemo, useRef, type ComponentType } from 'react';

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

export function DashboardPage() {
    const { state, notifyPlanLimit, clearPlanLimit } = useAuth();
    const analyticsEnabled = state.featureFlags.analytics_enabled !== false;
    const navigate = useNavigate();
    const metricsQuery = useDashboardMetrics(analyticsEnabled);
    const errorToastRef = useRef(false);

    useEffect(() => {
        if (!analyticsEnabled) {
            if (state.planLimit?.featureKey !== 'analytics_enabled') {
                notifyPlanLimit({
                    featureKey: 'analytics_enabled',
                    message: 'Upgrade your plan to unlock analytics dashboards and sourcing insights.',
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
            if (!errorToastRef.current && !(metricsQuery.error instanceof HttpError)) {
                publishToast({
                    variant: 'destructive',
                    title: 'Unable to load metrics',
                    description: 'We could not fetch the latest dashboard insights. Please retry.',
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
                        <CardDescription>{definition.description}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p className="text-3xl font-semibold tracking-tight">{value.toLocaleString()}</p>
                    </CardContent>
                </Card>
            );
        });
    }, [metricsQuery.data]);

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Dashboard</title>
            </Helmet>
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-foreground">Operations dashboard</h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Track sourcing throughput, keep tabs on downstream purchase order execution, and surface any
                        blockers before they impact fulfillment.
                    </p>
                </div>
                <Button asChild size="sm">
                    <Link to="/app/rfqs/new">
                        {/* TODO: clarify wizard route once RFQ creation flow ships */}
                        <LayoutDashboard className="mr-2 h-4 w-4" />
                        Create RFQ
                    </Link>
                </Button>
            </div>

            {analyticsEnabled ? (
                metricsQuery.isLoading || metricsQuery.isPlaceholderData ? (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {METRIC_DEFINITIONS.map((definition) => (
                            <Card key={definition.key} className="relative overflow-hidden">
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
                        ctaProps={{ onClick: () => metricsQuery.refetch(), variant: 'outline' }}
                    />
                ) : (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">{metricCards}</div>
                )
            ) : null}

            {!analyticsEnabled ? (
                <EmptyState
                    title="Analytics upgrade required"
                    description="Your current plan does not include the analytics dashboard. Upgrade to unlock sourcing insights."
                    icon={<TriangleAlert className="h-6 w-6" />}
                    ctaLabel="View plans"
                    ctaProps={{ variant: 'outline', onClick: () => navigate('/app/settings?tab=billing') }}
                />
            ) : null}
        </div>
    );
}
