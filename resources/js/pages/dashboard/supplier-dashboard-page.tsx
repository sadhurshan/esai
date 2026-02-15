import {
    ClipboardList,
    FileSpreadsheet,
    FileText,
    Inbox,
    PackageCheck,
    Wallet,
} from 'lucide-react';
import { useMemo } from 'react';
import { Helmet } from 'react-helmet-async';

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
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { useSupplierDashboardMetrics } from '@/hooks/api/useSupplierDashboardMetrics';
import { Link, useNavigate } from 'react-router-dom';

const METRIC_CARDS = [
    {
        key: 'rfqInvitationCount' as const,
        label: 'New RFQ invites',
        description: 'Inbound sourcing events waiting for your response.',
        icon: Inbox,
        href: '/app/supplier/rfqs',
    },
    {
        key: 'quotesDraftCount' as const,
        label: 'Draft quotes',
        description: 'Work-in-progress submissions not yet sent to buyers.',
        icon: FileText,
        href: '/app/supplier/quotes',
    },
    {
        key: 'quotesSubmittedCount' as const,
        label: 'Submitted quotes',
        description: 'Quotes awaiting buyer review or award decisions.',
        icon: FileSpreadsheet,
        href: '/app/supplier/quotes',
    },
    {
        key: 'purchaseOrdersPendingAckCount' as const,
        label: 'POs pending acknowledgement',
        description: 'New purchase orders that need confirmation.',
        icon: ClipboardList,
        href: '/app/supplier/orders',
    },
    {
        key: 'invoicesUnpaidCount' as const,
        label: 'Unpaid invoices',
        description: 'Receivables still outstanding with buyers.',
        icon: Wallet,
        href: '/app/invoices',
    },
];

const CHART_LABELS = ['Wk 1', 'Wk 2', 'Wk 3', 'Wk 4', 'Wk 5', 'Wk 6'];

function buildTrendSeries(value: number) {
    const multipliers = [0.5, 0.6, 0.7, 0.8, 0.9, 1];
    return multipliers.map((multiplier) =>
        Math.max(0, Math.round(value * multiplier)),
    );
}

export function SupplierDashboardPage() {
    const { activePersona, state } = useAuth();
    const { formatNumber } = useFormatting();
    const isSupplierPersona = activePersona?.type === 'supplier';
    const supplierStatus = state.company?.supplier_status ?? null;
    const isSupplierStart =
        state.company?.start_mode === 'supplier' ||
        (supplierStatus && supplierStatus !== 'none');
    const isSupplierMode = isSupplierPersona || isSupplierStart;
    const canViewMetrics = isSupplierPersona && supplierStatus === 'approved';
    const metricsQuery = useSupplierDashboardMetrics(canViewMetrics);
    const navigate = useNavigate();

    const cards = useMemo(() => {
        const metrics = metricsQuery.data;

        return METRIC_CARDS.map((definition) => {
            const Icon = definition.icon;
            const value = metrics?.[definition.key] ?? 0;

            return (
                <Card key={definition.key} className="relative">
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
                    <CardContent className="flex items-end justify-between">
                        <p className="text-3xl font-semibold tracking-tight">
                            {formatNumber(value, { maximumFractionDigits: 0 })}
                        </p>
                        <Button asChild size="sm" variant="outline">
                            <Link to={definition.href}>View</Link>
                        </Button>
                    </CardContent>
                </Card>
            );
        });
    }, [formatNumber, metricsQuery.data]);

    const chartData = useMemo(() => {
        if (!metricsQuery.data) {
            return [];
        }

        const rfqs = buildTrendSeries(
            metricsQuery.data.rfqInvitationCount ?? 0,
        );
        const draftQuotes = buildTrendSeries(
            metricsQuery.data.quotesDraftCount ?? 0,
        );
        const submittedQuotes = buildTrendSeries(
            metricsQuery.data.quotesSubmittedCount ?? 0,
        );
        const pos = buildTrendSeries(
            metricsQuery.data.purchaseOrdersPendingAckCount ?? 0,
        );
        const invoices = buildTrendSeries(
            metricsQuery.data.invoicesUnpaidCount ?? 0,
        );

        return CHART_LABELS.map((label, index) => ({
            label,
            rfqs: rfqs[index],
            draftQuotes: draftQuotes[index],
            submittedQuotes: submittedQuotes[index],
            pos: pos[index],
            invoices: invoices[index],
        }));
    }, [metricsQuery.data]);

    const showEmptyState = !isSupplierMode;
    const showApplyState = isSupplierStart && supplierStatus !== 'approved';

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Supplier Dashboard</title>
            </Helmet>

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-foreground">
                        Supplier workspace
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Track invitations, quotes, purchase orders, and
                        receivables from your buyers in one view.
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        type="button"
                        onClick={() => navigate('/app/supplier/quotes')}
                    >
                        Manage quotes
                    </Button>
                </div>
            </div>

            {showEmptyState ? (
                <EmptyState
                    icon={<PackageCheck className="h-6 w-6" />}
                    title="Switch to supplier persona"
                    description="You need to switch to a supplier persona to view supplier metrics."
                />
            ) : showApplyState ? (
                <EmptyState
                    icon={<PackageCheck className="h-6 w-6" />}
                    title="Complete your supplier application"
                    description="Submit your supplier profile to unlock dashboard metrics and buyer invitations."
                    ctaLabel="Complete supplier profile"
                    ctaProps={{
                        onClick: () =>
                            navigate('/app/supplier/company-profile'),
                        variant: 'outline',
                    }}
                />
            ) : metricsQuery.isLoading || metricsQuery.isPlaceholderData ? (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {METRIC_CARDS.map((card) => (
                        <Card key={card.key}>
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
                    icon={<PackageCheck className="h-6 w-6" />}
                    title="Unable to load supplier metrics"
                    description="Please refresh the page to try again."
                    ctaLabel="Retry"
                    ctaProps={{
                        onClick: () => metricsQuery.refetch(),
                        variant: 'outline',
                    }}
                />
            ) : (
                <div className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {cards}
                    </div>
                    <div className="grid gap-4 lg:grid-cols-2">
                        <MiniChart
                            title="RFQ & quote activity"
                            description="Track incoming RFQs and quote progress over time."
                            data={chartData}
                            series={[
                                {
                                    key: 'rfqs',
                                    label: 'RFQ invites',
                                    color: '#2563eb',
                                },
                                {
                                    key: 'submittedQuotes',
                                    label: 'Submitted quotes',
                                    color: '#16a34a',
                                },
                                {
                                    key: 'draftQuotes',
                                    label: 'Draft quotes',
                                    color: '#f97316',
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
                            title="Orders & receivables"
                            description="Purchase orders awaiting action and unpaid invoices."
                            data={chartData}
                            series={[
                                {
                                    key: 'pos',
                                    label: 'POs pending acknowledgement',
                                    color: '#0ea5e9',
                                    type: 'bar',
                                },
                                {
                                    key: 'invoices',
                                    label: 'Unpaid invoices',
                                    color: '#a855f7',
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
            )}
        </div>
    );
}
