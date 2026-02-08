import { useCallback, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link } from 'react-router-dom';
import { PackageSearch, RotateCcw, UsersRound } from 'lucide-react';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { SupplierDirectoryPicker } from '@/components/rfqs/supplier-directory-picker';
import { Badge } from '@/components/ui/badge';
import { useFormatting } from '@/contexts/formatting-context';
import { OrderStatusBadge } from '@/components/orders/order-status-badge';
import { useBuyerOrders } from '@/hooks/api/orders/use-buyer-orders';
import type { SalesOrderStatus, SalesOrderSummary } from '@/types/orders';
import type { Supplier } from '@/types/sourcing';

const STATUS_FILTERS: Array<{ value: 'all' | SalesOrderStatus; label: string }> = [
    { value: 'all', label: 'All' },
    { value: 'pending_ack', label: 'Pending ack' },
    { value: 'accepted', label: 'Accepted' },
    { value: 'partially_fulfilled', label: 'Partial' },
    { value: 'fulfilled', label: 'Fulfilled' },
    { value: 'cancelled', label: 'Cancelled' },
];

const PER_PAGE = 25;

export function BuyerOrderListPage() {
    const { formatDate, formatMoney } = useFormatting();

    const [statusFilter, setStatusFilter] = useState<(typeof STATUS_FILTERS)[number]['value']>('all');
    const [supplierPickerOpen, setSupplierPickerOpen] = useState(false);
    const [selectedSupplier, setSelectedSupplier] = useState<Supplier | null>(null);
    const [issuedFrom, setIssuedFrom] = useState('');
    const [issuedTo, setIssuedTo] = useState('');
    const [cursor, setCursor] = useState<string | null>(null);

    const buyerOrdersQuery = useBuyerOrders({
        cursor,
        perPage: PER_PAGE,
        status: statusFilter === 'all' ? undefined : statusFilter,
        supplierCompanyId: selectedSupplier?.id,
        dateFrom: issuedFrom || undefined,
        dateTo: issuedTo || undefined,
    });

    const orders = buyerOrdersQuery.data?.items ?? [];
    const cursorMeta = buyerOrdersQuery.data?.meta;
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
                    <Link className="font-semibold text-primary" to={`/app/orders/${order.id}`}>
                        {order.soNumber}
                    </Link>
                ),
            },
            {
                key: 'supplier',
                title: 'Supplier',
                render: (order) => order.supplierCompanyName ?? `Supplier #${order.supplierCompanyId}`,
            },
            {
                key: 'issueDate',
                title: 'Issue date',
                render: (order) => formatDate(order.issueDate),
            },
            {
                key: 'total',
                title: 'Order total',
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
                                                className="h-full rounded-full bg-sky-500"
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
                key: 'shipments',
                title: 'Shipments',
                render: (order) => (
                    <Badge variant="outline" className="font-mono text-xs">
                        {order.shipmentsCount ?? 0}
                    </Badge>
                ),
            },
            {
                key: 'lastEvent',
                title: 'Last event',
                render: (order) => formatDate(order.lastEventAt),
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
                        <Link to={`/app/orders/${order.id}`}>Open</Link>
                    </Button>
                ),
            },
        ],
        [formatDate, formatMoneyMinor],
    );

    const handleSupplierSelected = (supplier: Supplier) => {
        setSelectedSupplier(supplier);
        setCursor(null);
        setSupplierPickerOpen(false);
    };

    const handleClearSupplier = () => {
        setSelectedSupplier(null);
        setCursor(null);
    };

    const handleResetFilters = () => {
        setStatusFilter('all');
        setSelectedSupplier(null);
        setIssuedFrom('');
        setIssuedTo('');
        setCursor(null);
    };

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Orders</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="space-y-1">
                <p className="text-xs uppercase tracking-wide text-muted-foreground">Buyer workspace</p>
                <h1 className="text-2xl font-semibold text-foreground">Orders</h1>
                <p className="text-sm text-muted-foreground">
                    Track supplier acknowledgements, shipment progress, and delivery milestones per PO.
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
                            <label className="text-xs font-medium uppercase text-muted-foreground">Supplier filter</label>
                            <Button
                                type="button"
                                variant="outline"
                                className="justify-between"
                                onClick={() => setSupplierPickerOpen(true)}
                            >
                                <span className="truncate">
                                    {selectedSupplier ? selectedSupplier.name : 'Select supplier'}
                                </span>
                                <UsersRound className="ml-2 h-4 w-4 text-muted-foreground" />
                            </Button>
                            {selectedSupplier ? (
                                <Button type="button" size="sm" variant="ghost" onClick={handleClearSupplier}>
                                    Clear selection
                                </Button>
                            ) : (
                                <p className="text-xs text-muted-foreground">Filter by supplier directory entry.</p>
                            )}
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
                isLoading={buyerOrdersQuery.isLoading}
                emptyState={
                    <EmptyState
                        title="No activity yet"
                        description="Once suppliers acknowledge your POs you will see mirrored sales orders and shipment updates here."
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
                        disabled={buyerOrdersQuery.isLoading || !prevCursor}
                    >
                        Previous
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => nextCursor && setCursor(nextCursor)}
                        disabled={buyerOrdersQuery.isLoading || !nextCursor}
                    >
                        Next
                    </Button>
                </div>
            </div>

            <SupplierDirectoryPicker
                open={supplierPickerOpen}
                onOpenChange={setSupplierPickerOpen}
                onSelect={handleSupplierSelected}
            />
        </div>
    );
}
