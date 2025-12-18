import { useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate } from 'react-router-dom';
import { ClipboardList, Filter, RotateCcw, X } from 'lucide-react';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { Pagination } from '@/components/pagination';
import { EmptyState } from '@/components/empty-state';
import { PoStatusBadge } from '@/components/pos/po-status-badge';
import { MoneyCell } from '@/components/quotes/money-cell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Card, CardContent } from '@/components/ui/card';
import { useAuth } from '@/contexts/auth-context';
import { usePos, type UsePosParams } from '@/hooks/api/pos/use-pos';
import type { PurchaseOrderSummary, Supplier } from '@/types/sourcing';
import { formatDate } from '@/lib/format';
import { publishToast } from '@/components/ui/use-toast';
import { SupplierDirectoryPicker } from '@/components/rfqs/supplier-directory-picker';

const STATUS_FILTERS = [
    { value: 'all', label: 'All statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'sent', label: 'Sent' },
    { value: 'acknowledged', label: 'Acknowledged' },
    { value: 'fulfilled', label: 'Fulfilled' },
    { value: 'closed', label: 'Closed' },
    { value: 'cancelled', label: 'Cancelled' },
];

const ACK_STATUS_FILTERS = [
    { value: 'all', label: 'All acknowledgement states' },
    { value: 'draft', label: 'Draft (not sent)' },
    { value: 'sent', label: 'Sent' },
    { value: 'acknowledged', label: 'Acknowledged' },
    { value: 'declined', label: 'Declined' },
];

const PER_PAGE = 20;

export function PoListPage() {
    const navigate = useNavigate();
    const { hasFeature, state } = useAuth();
    const featureFlagsLoaded = state.status !== 'idle' && state.status !== 'loading';
    const poFeatureEnabled = hasFeature('purchase_orders');

    const [page, setPage] = useState(1);
    const [statusFilter, setStatusFilter] = useState<string>('all');
    const [supplierFilter, setSupplierFilter] = useState('');
    const [ackStatusFilter, setAckStatusFilter] = useState('all');
    const [selectedSupplier, setSelectedSupplier] = useState<Supplier | null>(null);
    const [supplierPickerOpen, setSupplierPickerOpen] = useState(false);
    const [issuedFrom, setIssuedFrom] = useState('');
    const [issuedTo, setIssuedTo] = useState('');

    const supplierId = useMemo(() => {
        if (supplierFilter.trim() === '') {
            return undefined;
        }

        const parsed = Number(supplierFilter);
        return Number.isFinite(parsed) ? parsed : undefined;
    }, [supplierFilter]);

    const posQuery = usePos({
        page,
        perPage: PER_PAGE,
        status: statusFilter as UsePosParams['status'],
        supplierId,
        issuedFrom: issuedFrom || undefined,
        issuedTo: issuedTo || undefined,
        ackStatus: ackStatusFilter as UsePosParams['ackStatus'],
    });

    const tableData = posQuery.items;

    const columns: DataTableColumn<PurchaseOrderSummary>[] = useMemo(
        () => [
            {
                key: 'poNumber',
                title: 'PO #',
                render: (po) => (
                    <Link className="font-semibold text-primary" to={`/app/purchase-orders/${po.id}`}>
                        {po.poNumber}
                    </Link>
                ),
            },
            {
                key: 'supplierName',
                title: 'Supplier',
                render: (po) => po.supplierName ?? '—',
            },
            {
                key: 'createdAt',
                title: 'Issued',
                render: (po) => formatDate(po.createdAt),
            },
            {
                key: 'currency',
                title: 'Currency',
                render: (po) => po.currency,
            },
            {
                key: 'total',
                title: 'Total',
                align: 'right',
                render: (po) => <MoneyCell amountMinor={po.totalMinor} currency={po.currency} label="PO total" />,
            },
            {
                key: 'status',
                title: 'Status',
                render: (po) => <PoStatusBadge status={po.status} />,
            },
            {
                key: 'actions',
                title: 'Actions',
                align: 'right',
                render: (po) => (
                    <Button asChild variant="ghost" size="sm">
                        <Link to={`/app/purchase-orders/${po.id}`}>Open</Link>
                    </Button>
                ),
            },
        ],
        [],
    );

    const paginationMeta = posQuery.meta
        ? {
              total: posQuery.meta.total,
              per_page: posQuery.meta.perPage,
              current_page: posQuery.meta.currentPage,
              last_page: posQuery.meta.lastPage,
          }
        : null;

    const handleResetFilters = () => {
        setStatusFilter('all');
        setSupplierFilter('');
        setAckStatusFilter('all');
        setSelectedSupplier(null);
        setIssuedFrom('');
        setIssuedTo('');
        setPage(1);
        publishToast({ variant: 'default', title: 'Filters cleared', description: 'Showing all purchase orders.' });
    };

    const handleSupplierSelected = (supplier: Supplier) => {
        setSelectedSupplier(supplier);
        setSupplierFilter(String(supplier.id));
        setPage(1);
    };

    const handleClearSupplier = () => {
        setSelectedSupplier(null);
        setSupplierFilter('');
        setPage(1);
    };

    if (featureFlagsLoaded && !poFeatureEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Purchase Orders</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Purchase orders unavailable"
                    description="Upgrade your Elements Supply plan to access purchase order workflows."
                    icon={<ClipboardList className="h-10 w-10" />}
                    ctaLabel="View plans"
                    ctaProps={{ onClick: () => navigate('/app/settings/billing') }}
                />
            </div>
        );
    }

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Purchase Orders</title>
            </Helmet>

            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Procurement</p>
                    <h1 className="text-2xl font-semibold text-foreground">Purchase orders</h1>
                    <p className="text-sm text-muted-foreground">
                        Track issued POs, monitor supplier acknowledgements, and drill into line level pricing.
                    </p>
                </div>
                <Button variant="outline" size="sm" onClick={() => navigate('/app/rfqs')}>
                    Create from RFQ
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
                                setPage(1);
                            }}
                        >
                            <SelectTrigger className="h-9">
                                <SelectValue aria-label="Purchase order status filter" />
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
                        <label className="text-xs font-medium uppercase text-muted-foreground">Acknowledgement</label>
                        <Select
                            value={ackStatusFilter}
                            onValueChange={(value) => {
                                setAckStatusFilter(value);
                                setPage(1);
                            }}
                        >
                            <SelectTrigger className="h-9">
                                <SelectValue aria-label="Acknowledgement status filter" />
                            </SelectTrigger>
                            <SelectContent>
                                {ACK_STATUS_FILTERS.map((option) => (
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
                                <span>{selectedSupplier ? selectedSupplier.name : 'Browse supplier directory'}</span>
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
                        <p className="text-xs text-muted-foreground">
                            Filter results by supplier. Start typing to search your approved directory.
                        </p>
                    </div>
                    <div className="space-y-2">
                        <label className="text-xs font-medium uppercase text-muted-foreground">Issued from</label>
                        <Input
                            type="date"
                            value={issuedFrom}
                            onChange={(event) => {
                                setIssuedFrom(event.target.value);
                                setPage(1);
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
                                setPage(1);
                            }}
                        />
                    </div>
                    <div className="md:col-span-4 flex flex-wrap gap-2">
                        <Button type="button" variant="outline" size="sm" onClick={handleResetFilters}>
                            <RotateCcw className="mr-2 h-4 w-4" /> Reset filters
                        </Button>
                        <Button type="button" variant="ghost" size="sm" disabled>
                            <Filter className="mr-2 h-4 w-4" /> Advanced filters (coming soon)
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <DataTable
                data={tableData}
                columns={columns}
                isLoading={posQuery.isLoading}
                emptyState={
                    <EmptyState
                        title="No purchase orders yet"
                        description="Award RFQ lines and convert them to POs to see them listed here."
                        icon={<ClipboardList className="h-10 w-10 text-muted-foreground" />}
                        ctaLabel="Go to awards"
                        ctaProps={{ onClick: () => navigate('/app/rfqs') }}
                    />
                }
            />

            <Pagination meta={paginationMeta} onPageChange={setPage} isLoading={posQuery.isLoading} />

            <SupplierDirectoryPicker
                open={supplierPickerOpen}
                onOpenChange={setSupplierPickerOpen}
                onSelect={handleSupplierSelected}
                allowAllSuppliersOption
                onSelectAllSuppliers={handleClearSupplier}
            />
        </div>
    );
}
