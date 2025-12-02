import { useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate } from 'react-router-dom';
import { Filter, RotateCcw, Wallet, X } from 'lucide-react';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { Pagination } from '@/components/pagination';
import { EmptyState } from '@/components/empty-state';
import { MoneyCell } from '@/components/quotes/money-cell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { SupplierDirectoryPicker } from '@/components/rfqs/supplier-directory-picker';
import { useAuth } from '@/contexts/auth-context';
import { useInvoices } from '@/hooks/api/invoices/use-invoices';
import type { InvoiceSummary, Supplier } from '@/types/sourcing';
import { formatDate } from '@/lib/format';

const STATUS_FILTERS = [
    { value: 'all', label: 'All statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'submitted', label: 'Submitted' },
    { value: 'posted', label: 'Posted' },
    { value: 'approved', label: 'Approved' },
    { value: 'paid', label: 'Paid' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'disputed', label: 'Disputed' },
    { value: 'rejected', label: 'Rejected' },
];

const STATUS_VARIANTS: Record<string, 'default' | 'secondary' | 'outline' | 'destructive'> = {
    draft: 'secondary',
    submitted: 'outline',
    posted: 'outline',
    approved: 'default',
    paid: 'default',
    overdue: 'destructive',
    disputed: 'destructive',
    rejected: 'destructive',
};

const PER_PAGE = 20;

export function InvoiceListPage() {
    const navigate = useNavigate();
    const { hasFeature, state } = useAuth();
    const featureFlagsLoaded = state.status !== 'idle' && state.status !== 'loading';
    const invoicesEnabled = hasFeature('invoices_enabled');

    const [page, setPage] = useState(1);
    const [statusFilter, setStatusFilter] = useState<string>('all');
    const [supplierFilter, setSupplierFilter] = useState('');
    const [selectedSupplier, setSelectedSupplier] = useState<Supplier | null>(null);
    const [supplierPickerOpen, setSupplierPickerOpen] = useState(false);
    const [issuedFrom, setIssuedFrom] = useState('');
    const [issuedTo, setIssuedTo] = useState('');

    const supplierId = useMemo(() => {
        const parsed = Number(supplierFilter);
        return Number.isFinite(parsed) ? parsed : undefined;
    }, [supplierFilter]);

    const invoicesQuery = useInvoices({
        page,
        perPage: PER_PAGE,
        status: statusFilter,
        supplierId,
        issuedFrom: issuedFrom || undefined,
        issuedTo: issuedTo || undefined,
    });

    const tableData = invoicesQuery.data?.items ?? [];

    const columns: DataTableColumn<InvoiceSummary>[] = useMemo(
        () => [
            {
                key: 'invoiceNumber',
                title: 'Invoice #',
                render: (invoice) => (
                    <Link className="font-semibold text-primary" to={`/app/invoices/${invoice.id}`}>
                        {invoice.invoiceNumber}
                    </Link>
                ),
            },
            {
                key: 'supplierName',
                title: 'Supplier',
                render: (invoice) => invoice.supplier?.name ?? '—',
            },
            {
                key: 'invoiceDate',
                title: 'Invoice date',
                render: (invoice) => formatDate(invoice.invoiceDate),
            },
            {
                key: 'poNumber',
                title: 'PO #',
                render: (invoice) =>
                    invoice.purchaseOrder ? (
                        <Link className="text-primary" to={`/app/purchase-orders/${invoice.purchaseOrder.id}`}>
                            {invoice.purchaseOrder.poNumber ?? `PO-${invoice.purchaseOrder.id}`}
                        </Link>
                    ) : (
                        '—'
                    ),
            },
            {
                key: 'currency',
                title: 'Currency',
                render: (invoice) => invoice.currency,
            },
            {
                key: 'total',
                title: 'Total',
                align: 'right',
                render: (invoice) => (
                    <MoneyCell
                        amountMinor={invoice.totalMinor ?? Math.round((invoice.total ?? 0) * 100)}
                        currency={invoice.currency}
                        label="Invoice total"
                    />
                ),
            },
            {
                key: 'status',
                title: 'Status',
                render: (invoice) => (
                    <Badge variant={STATUS_VARIANTS[invoice.status] ?? 'outline'} className="uppercase tracking-wide">
                        {invoice.status}
                    </Badge>
                ),
            },
            {
                key: 'actions',
                title: 'Actions',
                align: 'right',
                render: (invoice) => (
                    <Button asChild variant="ghost" size="sm">
                        <Link to={`/app/invoices/${invoice.id}`}>Open</Link>
                    </Button>
                ),
            },
        ],
        [],
    );

    const paginationMeta = invoicesQuery.data?.meta ?? null;

    const handleResetFilters = () => {
        setStatusFilter('all');
        setSupplierFilter('');
        setSelectedSupplier(null);
        setIssuedFrom('');
        setIssuedTo('');
        setPage(1);
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

    if (featureFlagsLoaded && !invoicesEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Invoices</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Invoices unavailable"
                    description="Upgrade your Elements Supply plan to unlock invoice tracking and matching."
                    icon={<Wallet className="h-10 w-10" />}
                    ctaLabel="View plans"
                    ctaProps={{ onClick: () => navigate('/app/settings/billing') }}
                />
            </div>
        );
    }

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Invoices</title>
            </Helmet>

            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Finance</p>
                    <h1 className="text-2xl font-semibold text-foreground">Invoices</h1>
                    <p className="text-sm text-muted-foreground">
                        Track invoice submissions tied to purchase orders and monitor approval status.
                    </p>
                </div>
                <Button variant="outline" size="sm" onClick={() => navigate('/app/purchase-orders')}>
                    Go to purchase orders
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
                                <SelectValue aria-label="Invoice status filter" />
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
                            Filter invoices by supplier from your approved directory.
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
                    <div className="md:col-span-5 flex flex-wrap gap-2">
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
                isLoading={invoicesQuery.isLoading}
                emptyState={
                    <EmptyState
                        title="No invoices yet"
                        description="Convert purchase orders into payable invoices to see them listed here."
                        icon={<Wallet className="h-10 w-10 text-muted-foreground" />}
                        ctaLabel="Browse purchase orders"
                        ctaProps={{ onClick: () => navigate('/app/purchase-orders') }}
                    />
                }
            />

            <Pagination meta={paginationMeta} onPageChange={setPage} isLoading={invoicesQuery.isLoading} />

            <SupplierDirectoryPicker open={supplierPickerOpen} onOpenChange={setSupplierPickerOpen} onSelect={handleSupplierSelected} />
        </div>
    );
}
