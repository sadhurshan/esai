import { Filter, RotateCcw, Wallet } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate } from 'react-router-dom';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { MoneyCell } from '@/components/quotes/money-cell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { useSupplierInvoices } from '@/hooks/api/invoices/use-supplier-invoices';
import { cn } from '@/lib/utils';
import type { InvoiceSummary } from '@/types/sourcing';

const STATUS_FILTERS = [
    { value: 'all', label: 'All statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'submitted', label: 'Submitted' },
    { value: 'buyer_review', label: 'Buyer review' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'paid', label: 'Paid' },
];

type BadgeVariant = 'default' | 'secondary' | 'outline' | 'destructive';

const STATUS_BADGES: Record<
    string,
    { variant: BadgeVariant; className?: string }
> = {
    draft: { variant: 'secondary' },
    submitted: {
        variant: 'outline',
        className: 'border-amber-200 bg-amber-50 text-amber-800',
    },
    buyer_review: {
        variant: 'outline',
        className: 'border-amber-200 bg-amber-50 text-amber-800',
    },
    approved: { variant: 'default' },
    rejected: { variant: 'destructive' },
    paid: { variant: 'default' },
};

const PER_PAGE = 25;

type CursorMeta = {
    next_cursor?: string | null;
    prev_cursor?: string | null;
    nextCursor?: string | null;
    prevCursor?: string | null;
    [key: string]: unknown;
};

export function SupplierInvoiceListPage() {
    const { hasFeature, state, activePersona } = useAuth();
    const { formatDate } = useFormatting();
    const navigate = useNavigate();
    const featureFlagsLoaded =
        state.status !== 'idle' && state.status !== 'loading';
    const supplierRole = state.user?.role === 'supplier';
    const isSupplierPersona = activePersona?.type === 'supplier';
    const supplierPortalEligible =
        supplierRole ||
        isSupplierPersona ||
        hasFeature('supplier_portal_enabled');
    const supplierInvoicingEnabled =
        supplierPortalEligible && hasFeature('supplier_invoicing_enabled');

    const [statusFilter, setStatusFilter] = useState<string>('all');
    const [poFilter, setPoFilter] = useState('');
    const [searchInput, setSearchInput] = useState('');
    const [searchTerm, setSearchTerm] = useState('');
    const [cursor, setCursor] = useState<string | null>(null);

    useEffect(() => {
        const handle = window.setTimeout(() => {
            setSearchTerm(searchInput.trim());
            setCursor(null);
        }, 250);

        return () => window.clearTimeout(handle);
    }, [searchInput]);

    const invoicesQuery = useSupplierInvoices({
        cursor,
        perPage: PER_PAGE,
        status: statusFilter,
        poNumber: poFilter.trim() || undefined,
        search: searchTerm || undefined,
    });

    const invoices = invoicesQuery.data?.items ?? [];
    const cursorMeta = (invoicesQuery.data?.meta ?? null) as CursorMeta | null;
    const nextCursor =
        typeof cursorMeta?.next_cursor === 'string'
            ? cursorMeta.next_cursor
            : typeof cursorMeta?.nextCursor === 'string'
              ? cursorMeta.nextCursor
              : null;
    const prevCursor =
        typeof cursorMeta?.prev_cursor === 'string'
            ? cursorMeta.prev_cursor
            : typeof cursorMeta?.prevCursor === 'string'
              ? cursorMeta.prevCursor
              : null;

    const columns: DataTableColumn<InvoiceSummary>[] = useMemo(
        () => [
            {
                key: 'invoiceNumber',
                title: 'Invoice',
                render: (invoice) => (
                    <div className="flex flex-col gap-1">
                        <Link
                            className="font-semibold text-primary"
                            to={`/app/supplier/invoices/${invoice.id}`}
                        >
                            {invoice.invoiceNumber}
                        </Link>
                        <span className="text-xs text-muted-foreground">
                            Submitted{' '}
                            {formatDate(
                                invoice.submittedAt ?? invoice.createdAt,
                            )}
                        </span>
                    </div>
                ),
            },
            {
                key: 'poNumber',
                title: 'Purchase order',
                render: (invoice) =>
                    invoice.purchaseOrder?.poNumber ??
                    `PO-${invoice.purchaseOrderId}`,
            },
            {
                key: 'dueDate',
                title: 'Due date',
                render: (invoice) => formatDate(invoice.dueDate),
            },
            {
                key: 'totalMinor',
                title: 'Total',
                render: (invoice) => (
                    <MoneyCell
                        amountMinor={
                            invoice.totalMinor ??
                            Math.round((invoice.total ?? 0) * 100)
                        }
                        currency={invoice.currency}
                        label="Invoice total"
                    />
                ),
            },
            {
                key: 'status',
                title: 'Status',
                align: 'right',
                render: (invoice) => {
                    const badge = STATUS_BADGES[invoice.status] ?? {
                        variant: 'outline',
                    };
                    return (
                        <div className="flex flex-col items-end gap-1">
                            <Badge
                                variant={badge.variant}
                                className={cn(
                                    'tracking-wide uppercase',
                                    badge.className,
                                )}
                            >
                                {formatStatus(invoice.status)}
                            </Badge>
                            {invoice.reviewNote &&
                            (invoice.status === 'buyer_review' ||
                                invoice.status === 'rejected') ? (
                                <p className="line-clamp-2 text-xs text-muted-foreground">
                                    {invoice.reviewNote}
                                </p>
                            ) : null}
                        </div>
                    );
                },
            },
            {
                key: 'actions',
                title: 'Actions',
                align: 'right',
                render: (invoice) => (
                    <Button asChild size="sm" variant="ghost">
                        <Link to={`/app/supplier/invoices/${invoice.id}`}>
                            Open
                        </Link>
                    </Button>
                ),
            },
        ],
        [formatDate],
    );

    const handleResetFilters = () => {
        setStatusFilter('all');
        setPoFilter('');
        setSearchInput('');
        setSearchTerm('');
        setCursor(null);
    };

    if (featureFlagsLoaded && !supplierInvoicingEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Supplier invoices</title>
                </Helmet>
                <WorkspaceBreadcrumbs />
                <PlanUpgradeBanner />
                <EmptyState
                    title="Supplier portal unavailable"
                    description="This workspace plan does not include supplier-authored invoicing. Request an upgrade to enable it."
                    icon={<Wallet className="h-10 w-10 text-destructive" />}
                    ctaLabel="Back to dashboard"
                    ctaProps={{ onClick: () => navigate('/app') }}
                />
            </div>
        );
    }

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Supplier invoices</title>
            </Helmet>

            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        Supplier portal
                    </p>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Invoices
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Submit invoices against purchase orders and monitor
                        buyer review status.
                    </p>
                </div>
                <Button asChild type="button" size="sm">
                    <Link to="/app/supplier/invoices/create">
                        Create invoice
                    </Link>
                </Button>
            </div>

            <Card className="border-border/70">
                <CardContent className="grid gap-4 py-6 md:grid-cols-4">
                    <div className="space-y-2">
                        <label className="text-xs font-medium text-muted-foreground uppercase">
                            Status
                        </label>
                        <Select
                            value={statusFilter}
                            onValueChange={(value) => {
                                setStatusFilter(value);
                                setCursor(null);
                            }}
                        >
                            <SelectTrigger className="h-9">
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUS_FILTERS.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <label className="text-xs font-medium text-muted-foreground uppercase">
                            PO number
                        </label>
                        <Input
                            value={poFilter}
                            onChange={(event) => {
                                setPoFilter(event.target.value);
                                setCursor(null);
                            }}
                            placeholder="e.g. PO-1045"
                        />
                    </div>
                    <div className="space-y-2">
                        <label className="text-xs font-medium text-muted-foreground uppercase">
                            Search
                        </label>
                        <Input
                            value={searchInput}
                            onChange={(event) =>
                                setSearchInput(event.target.value)
                            }
                            placeholder="Invoice # or payment reference"
                        />
                    </div>
                    <div className="flex items-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={handleResetFilters}
                        >
                            <RotateCcw className="mr-2 h-4 w-4" /> Reset
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            disabled
                        >
                            <Filter className="mr-2 h-4 w-4" /> Advanced filters
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <DataTable
                data={invoices}
                columns={columns}
                isLoading={invoicesQuery.isLoading}
                emptyState={
                    <EmptyState
                        title="No invoices yet"
                        description="Start by converting a purchase order into an invoice once a delivery is in progress."
                        icon={
                            <Wallet className="h-10 w-10 text-muted-foreground" />
                        }
                    />
                }
            />

            <div className="flex flex-col gap-2 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-xs text-muted-foreground">
                    Showing {invoices.length} result
                    {invoices.length === 1 ? '' : 's'} at a time.
                </p>
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={!prevCursor || invoicesQuery.isFetching}
                        onClick={() => prevCursor && setCursor(prevCursor)}
                    >
                        Previous
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={!nextCursor || invoicesQuery.isFetching}
                        onClick={() => nextCursor && setCursor(nextCursor)}
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    );
}

function formatStatus(status?: string | null): string {
    if (!status) {
        return 'unknown';
    }

    return status.replace(/_/g, ' ');
}
