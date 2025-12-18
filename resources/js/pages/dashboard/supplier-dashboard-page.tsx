import { useMemo } from 'react';
import { Helmet } from 'react-helmet-async';
import { ClipboardList, FileText, FileSpreadsheet, Inbox, PackageCheck, Wallet } from 'lucide-react';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { useSupplierDashboardMetrics } from '@/hooks/api/useSupplierDashboardMetrics';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { Link } from 'react-router-dom';

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

export function SupplierDashboardPage() {
    const { activePersona } = useAuth();
    const { formatNumber } = useFormatting();
    const isSupplierPersona = activePersona?.type === 'supplier';
    const metricsQuery = useSupplierDashboardMetrics(isSupplierPersona);

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
                        <CardDescription>{definition.description}</CardDescription>
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

    const showEmptyState = !isSupplierPersona;

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Supplier Dashboard</title>
            </Helmet>

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-foreground">Supplier workspace</h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Track invitations, quotes, purchase orders, and receivables from your buyers in one view.
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button asChild variant="outline" size="sm">
                        <Link to="/app/supplier/quotes">Manage quotes</Link>
                    </Button>
                </div>
            </div>

            {showEmptyState ? (
                <EmptyState
                    icon={<PackageCheck className="h-6 w-6" />}
                    title="Switch to supplier persona"
                    description="You need to switch to a supplier persona to view supplier metrics."
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
                    ctaProps={{ onClick: () => metricsQuery.refetch(), variant: 'outline' }}
                />
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {cards}
                </div>
            )}
        </div>
    );
}
