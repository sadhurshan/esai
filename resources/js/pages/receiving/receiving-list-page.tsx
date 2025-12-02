import { useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate } from 'react-router-dom';
import { Filter, PackageCheck, PackagePlus, RotateCcw, Truck, X } from 'lucide-react';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { SupplierDirectoryPicker } from '@/components/rfqs/supplier-directory-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { useGrns } from '@/hooks/api/receiving/use-grns';
import type { GoodsReceiptNoteSummary, Supplier } from '@/types/sourcing';
import { GrnStatusBadge } from '@/components/receiving/grn-status-badge';

const STATUS_FILTERS = [
    { value: 'all', label: 'All statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'posted', label: 'Posted' },
];

const PER_PAGE = 25;

type CursorMeta = {
    next_cursor?: string | null;
    prev_cursor?: string | null;
    [key: string]: unknown;
};

export function ReceivingListPage() {
    const navigate = useNavigate();
    const { hasFeature, state } = useAuth();
    const { formatDate, formatNumber } = useFormatting();
    const featureFlagsLoaded = state.status !== 'idle' && state.status !== 'loading';
    const receivingEnabled = hasFeature('inventory_enabled');

    const [statusFilter, setStatusFilter] = useState('all');
    const [supplierPickerOpen, setSupplierPickerOpen] = useState(false);
    const [selectedSupplier, setSelectedSupplier] = useState<Supplier | null>(null);
    const [supplierFilter, setSupplierFilter] = useState('');
    const [poFilter, setPoFilter] = useState('');
    const [receivedFrom, setReceivedFrom] = useState('');
    const [receivedTo, setReceivedTo] = useState('');
    const [cursor, setCursor] = useState<string | null>(null);

    const supplierId = useMemo(() => {
        const parsed = Number(supplierFilter);
        return Number.isFinite(parsed) ? parsed : undefined;
    }, [supplierFilter]);

    const purchaseOrderId = useMemo(() => {
        const parsed = Number(poFilter);
        return Number.isFinite(parsed) ? parsed : undefined;
    }, [poFilter]);

    const grnsQuery = useGrns({
        cursor,
        perPage: PER_PAGE,
        status: statusFilter === 'all' ? undefined : statusFilter,
        supplierId,
        purchaseOrderId,
        receivedFrom: receivedFrom || undefined,
        receivedTo: receivedTo || undefined,
    });

    const grns = grnsQuery.data?.items ?? [];
    const cursorMeta = (grnsQuery.data?.meta ?? null) as CursorMeta | null;
    const nextCursor = typeof cursorMeta?.next_cursor === 'string' ? cursorMeta.next_cursor : null;
    const prevCursor = typeof cursorMeta?.prev_cursor === 'string' ? cursorMeta.prev_cursor : null;

    const columns: DataTableColumn<GoodsReceiptNoteSummary>[] = useMemo(
        () => [
            {
                key: 'grnNumber',
                title: 'GRN #',
                render: (grn) => (
                    <Link className="font-semibold text-primary" to={`/app/receiving/${grn.id}`}>
                        {grn.grnNumber}
                    </Link>
                ),
            },
            {
                key: 'purchaseOrderNumber',
                title: 'PO #',
                render: (grn) =>
                    grn.purchaseOrderNumber ? (
                        <Link className="text-primary" to={`/app/purchase-orders/${grn.purchaseOrderId}`}>
                            {grn.purchaseOrderNumber}
                        </Link>
                    ) : (
                        <span className="text-muted-foreground">PO-{grn.purchaseOrderId}</span>
                    ),
            },
            {
                key: 'supplierName',
                title: 'Supplier',
                render: (grn) => grn.supplierName ?? '—',
            },
            {
                key: 'receivedAt',
                title: 'Received at',
                render: (grn) => formatDate(grn.receivedAt ?? grn.postedAt),
            },
            {
                key: 'linesCount',
                title: 'Lines',
                render: (grn) => (typeof grn.linesCount === 'number' ? formatNumber(grn.linesCount, { maximumFractionDigits: 0 }) : '—'),
            },
            {
                key: 'status',
                title: 'Status',
                render: (grn) => <GrnStatusBadge status={grn.status} />,
            },
            {
                key: 'attachmentsCount',
                title: 'Attachments',
                render: (grn) => (
                    <Badge variant="outline" className="font-mono text-xs">
                        {formatNumber(grn.attachmentsCount ?? 0, { maximumFractionDigits: 0 })}
                    </Badge>
                ),
            },
            {
                key: 'actions',
                title: 'Actions',
                align: 'right',
                render: (grn) => (
                    <Button asChild size="sm" variant="ghost">
                        <Link to={`/app/receiving/${grn.id}`}>Open</Link>
                    </Button>
                ),
            },
        ],
        [formatDate, formatNumber],
    );

    const handleResetFilters = () => {
        setStatusFilter('all');
        setSupplierPickerOpen(false);
        setSelectedSupplier(null);
        setSupplierFilter('');
        setPoFilter('');
        setReceivedFrom('');
        setReceivedTo('');
        setCursor(null);
    };

    const handleSupplierSelected = (supplier: Supplier) => {
        setSelectedSupplier(supplier);
        setSupplierFilter(String(supplier.id));
        setCursor(null);
    };

    const handleClearSupplier = () => {
        setSelectedSupplier(null);
        setSupplierFilter('');
        setCursor(null);
    };

    if (featureFlagsLoaded && !receivingEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Receiving</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Receiving unavailable"
                    description="Upgrade your Elements Supply plan to enable goods receipt workflows."
                    icon={<Truck className="h-12 w-12 text-muted-foreground" />}
                    ctaLabel="View plans"
                    ctaProps={{ onClick: () => navigate('/app/settings/billing') }}
                />
            </div>
        );
    }

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Receiving</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Operations</p>
                    <h1 className="text-2xl font-semibold text-foreground">Receiving</h1>
                    <p className="text-sm text-muted-foreground">
                        Record goods receipt notes and reconcile deliveries against purchase orders.
                    </p>
                </div>
                <Button type="button" size="sm" onClick={() => navigate('/app/receiving/new')}>
                    <PackagePlus className="mr-2 h-4 w-4" /> Record receipt
                </Button>
            </div>

            <Card className="border-border/70">
                <CardContent className="grid gap-4 py-6 md:grid-cols-5">
                    <div className="space-y-2">
                        <label className="text-xs font-medium uppercase text-muted-foreground">Status</label>
                        <Select
                            value={statusFilter}
                            onValueChange={(value) => {
                                setStatusFilter(value);
                                setCursor(null);
                            }}
                        >
                            <SelectTrigger className="h-9">
                                <SelectValue aria-label="GRN status filter" />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUS_FILTERS.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <label className="text-xs font-medium uppercase text-muted-foreground">Supplier</label>
                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className="flex-1 justify-between"
                                onClick={() => setSupplierPickerOpen(true)}
                            >
                                <span>{selectedSupplier ? selectedSupplier.name : 'Select supplier'}</span>
                            </Button>
                            {selectedSupplier ? (
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    aria-label="Clear supplier filter"
                                    onClick={handleClearSupplier}
                                >
                                    <X className="h-4 w-4" />
                                </Button>
                            ) : null}
                        </div>
                        <p className="text-xs text-muted-foreground">Filter GRNs by supplier directory entries.</p>
                    </div>
                    <div className="space-y-2">
                        <label className="text-xs font-medium uppercase text-muted-foreground">PO number</label>
                        <Input
                            type="number"
                            value={poFilter}
                            onChange={(event) => {
                                setPoFilter(event.target.value);
                                setCursor(null);
                            }}
                            placeholder="Enter PO id"
                            min={0}
                        />
                    </div>
                    <div className="space-y-2">
                        <label className="text-xs font-medium uppercase text-muted-foreground">Received from</label>
                        <Input
                            type="date"
                            value={receivedFrom}
                            onChange={(event) => {
                                setReceivedFrom(event.target.value);
                                setCursor(null);
                            }}
                        />
                    </div>
                    <div className="space-y-2">
                        <label className="text-xs font-medium uppercase text-muted-foreground">Received to</label>
                        <Input
                            type="date"
                            value={receivedTo}
                            onChange={(event) => {
                                setReceivedTo(event.target.value);
                                setCursor(null);
                            }}
                        />
                    </div>
                    <div className="md:col-span-5 flex flex-wrap gap-2">
                        <Button type="button" variant="outline" size="sm" onClick={handleResetFilters}>
                            <RotateCcw className="mr-2 h-4 w-4" /> Reset filters
                        </Button>
                        <Button type="button" variant="ghost" size="sm" disabled>
                            <Filter className="mr-2 h-4 w-4" /> Saved views (coming soon)
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <DataTable
                data={grns}
                columns={columns}
                isLoading={grnsQuery.isLoading}
                emptyState={
                    <EmptyState
                        title="No goods receipt notes"
                        description="Draft a GRN when items arrive against a purchase order to keep inventory reconciled."
                        icon={<PackageCheck className="h-12 w-12 text-muted-foreground" />}
                        ctaLabel="Record receipt"
                        ctaProps={{ onClick: () => navigate('/app/receiving/new') }}
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
                        onClick={() => setCursor(prevCursor ?? null)}
                        disabled={grnsQuery.isLoading || (!prevCursor && cursor === null)}
                    >
                        Previous
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => nextCursor && setCursor(nextCursor)}
                        disabled={grnsQuery.isLoading || !nextCursor}
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
