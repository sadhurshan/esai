import { useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate } from 'react-router-dom';
import { BadgeCheck, Filter, NotepadText, RotateCcw, Search, Wallet, X } from 'lucide-react';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { Pagination } from '@/components/pagination';
import { SupplierDirectoryPicker } from '@/components/rfqs/supplier-directory-picker';
import { MoneyCell } from '@/components/quotes/money-cell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { useAuth } from '@/contexts/auth-context';
import { useCreditNotes } from '@/hooks/api/credits/use-credit-notes';
import type { CreditNoteSummary, Supplier } from '@/types/sourcing';
import { formatDate } from '@/lib/format';

const STATUS_FILTERS = [
    { value: 'all', label: 'All statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'pending_review', label: 'Pending review' },
    { value: 'issued', label: 'Issued' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'applied', label: 'Applied' },
];

const STATUS_VARIANTS: Record<string, 'default' | 'secondary' | 'outline' | 'destructive'> = {
    draft: 'secondary',
    pending_review: 'outline',
    issued: 'outline',
    approved: 'default',
    applied: 'default',
    rejected: 'destructive',
};

const PER_PAGE = 20;

type PaginationMeta = {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
};

export function CreditNoteListPage() {
    const navigate = useNavigate();
    const { hasFeature, state } = useAuth();
    const featureFlagsLoaded = state.status !== 'idle' && state.status !== 'loading';
    const financeEnabled = hasFeature('finance_enabled');

    const [page, setPage] = useState(1);
    const [statusFilter, setStatusFilter] = useState('all');
    const [supplierPickerOpen, setSupplierPickerOpen] = useState(false);
    const [selectedSupplier, setSelectedSupplier] = useState<Supplier | null>(null);
    const [supplierFilter, setSupplierFilter] = useState('');
    const [createdFrom, setCreatedFrom] = useState('');
    const [createdTo, setCreatedTo] = useState('');
    const [searchTerm, setSearchTerm] = useState('');

    const supplierId = useMemo(() => {
        const parsed = Number(supplierFilter);
        return Number.isFinite(parsed) ? parsed : undefined;
    }, [supplierFilter]);

    const creditNotesQuery = useCreditNotes({
        page,
        perPage: PER_PAGE,
        status: statusFilter === 'all' ? undefined : statusFilter,
        supplierId,
        createdFrom: createdFrom || undefined,
        createdTo: createdTo || undefined,
        search: searchTerm.trim() || undefined,
    });

    const creditNotes = creditNotesQuery.data?.items ?? [];
    const paginationMeta = (creditNotesQuery.data?.meta ?? null) as PaginationMeta | null;

    const columns: DataTableColumn<CreditNoteSummary>[] = useMemo(
        () => [
            {
                key: 'creditNumber',
                title: 'Credit #',
                render: (credit) => (
                    <Link className="font-semibold text-primary" to={`/app/credit-notes/${credit.id}`}>
                        {credit.creditNumber}
                    </Link>
                ),
            },
            {
                key: 'supplierName',
                title: 'Supplier',
                render: (credit) => credit.supplierName ?? '—',
            },
            {
                key: 'invoiceNumber',
                title: 'Invoice #',
                render: (credit) =>
                    credit.invoiceId ? (
                        <Link className="text-primary" to={`/app/invoices/${credit.invoiceId}`}>
                            {credit.invoiceNumber ?? `INV-${credit.invoiceId}`}
                        </Link>
                    ) : (
                        '—'
                    ),
            },
            {
                key: 'createdAt',
                title: 'Created',
                render: (credit) => formatDate(credit.createdAt ?? credit.issuedAt),
            },
            {
                key: 'currency',
                title: 'Currency',
                render: (credit) => credit.currency ?? '—',
            },
            {
                key: 'totalMinor',
                title: 'Total',
                align: 'right',
                render: (credit) => (
                    <MoneyCell amountMinor={credit.totalMinor} currency={credit.currency} label="Credit total" />
                ),
            },
            {
                key: 'status',
                title: 'Status',
                render: (credit) => (
                    <Badge variant={STATUS_VARIANTS[credit.status] ?? 'outline'} className="uppercase tracking-wide">
                        {credit.status.replace(/_/g, ' ')}
                    </Badge>
                ),
            },
            {
                key: 'actions',
                title: 'Actions',
                align: 'right',
                render: (credit) => (
                    <Button asChild variant="ghost" size="sm">
                        <Link to={`/app/credit-notes/${credit.id}`}>Open</Link>
                    </Button>
                ),
            },
        ],
        [],
    );

    const handleResetFilters = () => {
        setPage(1);
        setStatusFilter('all');
        setSelectedSupplier(null);
        setSupplierFilter('');
        setSupplierPickerOpen(false);
        setCreatedFrom('');
        setCreatedTo('');
        setSearchTerm('');
    };

    const handleSupplierSelected = (supplier: Supplier) => {
        setSelectedSupplier(supplier);
        setSupplierFilter(String(supplier.id));
        setSupplierPickerOpen(false);
        setPage(1);
    };

    const handleClearSupplier = () => {
        setSelectedSupplier(null);
        setSupplierFilter('');
        setPage(1);
    };

    if (featureFlagsLoaded && !financeEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Credit Notes</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Credit notes unavailable"
                    description="Upgrade your Elements Supply plan to unlock finance reconciliation tools."
                    icon={<Wallet className="h-10 w-10 text-muted-foreground" />}
                    ctaLabel="View plans"
                    ctaProps={{ onClick: () => navigate('/app/settings?tab=billing') }}
                />
            </div>
        );
    }

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Credit Notes</title>
            </Helmet>

            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Finance</p>
                    <h1 className="text-2xl font-semibold text-foreground">Credit notes</h1>
                    <p className="text-sm text-muted-foreground">
                        Track credits issued against invoices and keep supplier balances up to date.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button type="button" variant="outline" size="sm" onClick={() => navigate('/app/matching')}>
                        <BadgeCheck className="mr-2 h-4 w-4" /> Match variances
                    </Button>
                    <Button type="button" size="sm" variant="default" onClick={() => navigate('/app/invoices')}>
                        <NotepadText className="mr-2 h-4 w-4" /> Create from invoice
                    </Button>
                </div>
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
                                <SelectValue aria-label="Credit note status filter" />
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
                        <p className="text-xs text-muted-foreground">Filter credits by supplier acknowledgement.</p>
                    </div>
                    <div className="space-y-2">
                        <label className="text-xs font-medium uppercase text-muted-foreground">Created from</label>
                        <Input
                            type="date"
                            value={createdFrom}
                            onChange={(event) => {
                                setCreatedFrom(event.target.value);
                                setPage(1);
                            }}
                        />
                    </div>
                    <div className="space-y-2">
                        <label className="text-xs font-medium uppercase text-muted-foreground">Created to</label>
                        <Input
                            type="date"
                            value={createdTo}
                            onChange={(event) => {
                                setCreatedTo(event.target.value);
                                setPage(1);
                            }}
                        />
                    </div>
                    <div className="space-y-2">
                        <label className="text-xs font-medium uppercase text-muted-foreground">Search</label>
                        <div className="relative">
                            <Search className="absolute left-2 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                className="pl-8"
                                placeholder="Search credit or invoice #"
                                value={searchTerm}
                                onChange={(event) => {
                                    setSearchTerm(event.target.value);
                                    setPage(1);
                                }}
                            />
                        </div>
                    </div>
                    <div className="md:col-span-5 flex flex-wrap gap-2">
                        <Button type="button" variant="outline" size="sm" onClick={handleResetFilters}>
                            <RotateCcw className="mr-2 h-4 w-4" /> Reset filters
                        </Button>
                        <Button type="button" variant="ghost" size="sm" disabled>
                            <Filter className="mr-2 h-4 w-4" /> Saved filters (coming soon)
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <DataTable
                data={creditNotes}
                columns={columns}
                isLoading={creditNotesQuery.isLoading}
                emptyState={
                    <EmptyState
                        title="No credit notes yet"
                        description="Create a credit note from an invoice variance to see it listed here."
                        icon={<NotepadText className="h-10 w-10 text-muted-foreground" />}
                        ctaLabel="Open matching workbench"
                        ctaProps={{ onClick: () => navigate('/app/matching') }}
                    />
                }
            />

            <Pagination meta={paginationMeta} onPageChange={setPage} isLoading={creditNotesQuery.isLoading} />

            <SupplierDirectoryPicker open={supplierPickerOpen} onOpenChange={setSupplierPickerOpen} onSelect={handleSupplierSelected} />
        </div>
    );
}
