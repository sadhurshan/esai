import { useCallback, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link } from 'react-router-dom';
import { PackageSearch, RotateCcw } from 'lucide-react';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useFormatting } from '@/contexts/formatting-context';
import { OrderStatusBadge } from '@/components/orders/order-status-badge';
import { useSupplierOrders } from '@/hooks/api/orders/use-supplier-orders';
import type { SalesOrderStatus, SalesOrderSummary } from '@/types/orders';

const STATUS_FILTERS: Array<{ value: 'all' | SalesOrderStatus; label: string }> = [
    { value: 'all', label: 'All' },
    { value: 'pending_ack', label: 'Pending ack' },
    { value: 'accepted', label: 'Accepted' },
    { value: 'partially_fulfilled', label: 'Partial' },
    { value: 'fulfilled', label: 'Fulfilled' },
    { value: 'cancelled', label: 'Cancelled' },
];

const PER_PAGE = 25;

export function SupplierOrderListPage() {
    const { formatMoney, formatDate } = useFormatting();

    const [statusFilter, setStatusFilter] = useState<(typeof STATUS_FILTERS)[number]['value']>('all');
    const [buyerFilter, setBuyerFilter] = useState('');
    const [issuedFrom, setIssuedFrom] = useState('');
    const [issuedTo, setIssuedTo] = useState('');
    const [cursor, setCursor] = useState<string | null>(null);

    const buyerCompanyId = useMemo(() => {
        const parsed = Number(buyerFilter);
        return Number.isFinite(parsed) ? parsed : undefined;
    }, [buyerFilter]);

    const supplierOrdersQuery = useSupplierOrders({
        cursor,
        perPage: PER_PAGE,
        status: statusFilter === 'all' ? undefined : statusFilter,
        buyerCompanyId,
        dateFrom: issuedFrom || undefined,
        dateTo: issuedTo || undefined,
    });

    const orders = supplierOrdersQuery.data?.items ?? [];
    const cursorMeta = supplierOrdersQuery.data?.meta;
    const nextCursor = cursorMeta?.nextCursor ?? null;
    const prevCursor = cursorMeta?.prevCursor ?? null;

    const formatMoneyMinor = useCallback((amountMinor?: number | null, currency?: string) => {
        if (amountMinor === undefined || amountMinor === null) {
            return '—';
        }
        return formatMoney(amountMinor / 100, { currency });
    }, [formatMoney]);

    const columns: DataTableColumn<SalesOrderSummary>[] = useMemo(
        () => [
            {
                key: 'soNumber',
                title: 'SO #',
                render: (order) => (
                    <Link className="font-semibold text-primary" to={`/app/supplier/orders/${order.id}`}>
                        {order.soNumber}
                    </Link>
                ),
            },
            {
                key: 'buyer',
                title: 'Buyer',
                render: (order) => order.buyerCompanyName ?? `Buyer #${order.buyerCompanyId}`,
            },
            {
                key: 'issueDate',
                title: 'Issue date',
                render: (order) => formatDate(order.issueDate),
            },
            {
                key: 'currency',
                title: 'Currency',
                render: (order) => order.currency,
            },
            {
                key: 'total',
                title: 'Total',
                render: (order) => formatMoneyMinor(order.totals?.totalMinor ?? null, order.currency),
            },
            {
                key: 'fulfillment',
                title: 'Fulfillment',
                render: (order) => {
                    const shipped = order.fulfillment?.shippedQty ?? 0;
                    const ordered = order.fulfillment?.orderedQty ?? 0;
                    const percent = Math.min(100, Math.round(order.fulfillment?.percent ?? (ordered ? (shipped / ordered) * 100 : 0)));

                    return (
                        <TooltipProvider delayDuration={150}>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <div className="flex flex-col gap-1">
                                        <div className="h-2 w-28 overflow-hidden rounded-full bg-muted">
                                            <div
                                                className="h-full rounded-full bg-emerald-500"
                                                style={{ width: `${percent}%` }}
                                                aria-label={`Fulfillment ${percent}%`}
                                            />
                                        </div>
                                        <span className="text-xs font-medium text-muted-foreground">{percent}%</span>
                                    </div>
                                </TooltipTrigger>
                                <TooltipContent>
                                    {shipped} of {ordered || '—'} units shipped
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    );
                },
            },
            {
                key: 'status',
                title: 'Status',
                render: (order) => <OrderStatusBadge status={order.status} />,
            },
            {
                key: 'actions',
                title: 'Actions',
                align: 'right',
                render: (order) => (
                    <Button asChild size="sm" variant="ghost">
                        <Link to={`/app/supplier/orders/${order.id}`}>Open</Link>
                    </Button>
                ),
            },
        ],
        [formatDate, formatMoneyMinor],
    );

    const handleResetFilters = () => {
        setStatusFilter('all');
        setBuyerFilter('');
        setIssuedFrom('');
        setIssuedTo('');
        setCursor(null);
    };

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Supplier Orders</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="space-y-1">
                <p className="text-xs uppercase tracking-wide text-muted-foreground">Supplier workspace</p>
                <h1 className="text-2xl font-semibold text-foreground">Sales orders</h1>
                <p className="text-sm text-muted-foreground">
                    Mirror of buyer purchase orders with acknowledgement and fulfillment tracking.
                </p>
            </div>

            <Card className="border-border/70">
                <CardContent className="space-y-5 py-6">
                    <div className="flex flex-wrap gap-2">
                        {STATUS_FILTERS.map((filter) => (
                            <Button
                                key={filter.value}
                                type="button"
                                size="sm"
                                variant={statusFilter === filter.value ? 'default' : 'outline'}
                                onClick={() => {
                                    setStatusFilter(filter.value);
                                    setCursor(null);
                                }}
                            >
                                {filter.label}
                            </Button>
                        ))}
                    </div>
                    <div className="grid gap-4 md:grid-cols-4">
                        <div className="space-y-2">
                            <label className="text-xs font-medium uppercase text-muted-foreground">Buyer company ID</label>
                            <Input
                                type="number"
                                value={buyerFilter}
                                onChange={(event) => {
                                    setBuyerFilter(event.target.value);
                                    setCursor(null);
                                }}
                                placeholder="Enter company id"
                                min={0}
                            />
                            {/* TODO: replace numeric buyer filter with directory picker once buyer lookup endpoint is available. */}
                        </div>
                        <div className="space-y-2">
                            <label className="text-xs font-medium uppercase text-muted-foreground">Issued from</label>
                            <Input
                                type="date"
                                value={issuedFrom}
                                onChange={(event) => {
                                    setIssuedFrom(event.target.value);
                                    setCursor(null);
                                }}
                            />
                        </div>
                        <div className="space-y-2">
                            <label className="text-xs font-medium uppercase text-muted-foreground">Issued to</label>
                            <Input
                                type="date"
                                value={issuedTo}
                                onChange={(event) => {
                                    setIssuedTo(event.target.value);
                                    setCursor(null);
                                }}
                            />
                        </div>
                        <div className="flex items-end">
                            <Button type="button" variant="outline" size="sm" onClick={handleResetFilters}>
                                <RotateCcw className="mr-2 h-4 w-4" /> Reset filters
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <DataTable
                data={orders}
                columns={columns}
                isLoading={supplierOrdersQuery.isLoading}
                emptyState={
                    <EmptyState
                        title="No sales orders"
                        description="You will see incoming buyer purchase orders here once procurement teams engage you."
                        icon={<PackageSearch className="h-12 w-12 text-muted-foreground" />}
                    />
                }
            />

            <div className="flex items-center justify-between rounded-lg border border-border/60 bg-background/60 px-3 py-2 text-sm">
                <span className="text-muted-foreground">Cursor-based pagination</span>
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => prevCursor && setCursor(prevCursor)}
                        disabled={supplierOrdersQuery.isLoading || !prevCursor}
                    >
                        Previous
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => nextCursor && setCursor(nextCursor)}
                        disabled={supplierOrdersQuery.isLoading || !nextCursor}
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    );
}
