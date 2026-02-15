import { FileText } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link } from 'react-router-dom';

import { DataTable, type DataTableColumn } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FilterBar, type FilterConfig } from '@/components/filter-bar';
import { Pagination } from '@/components/pagination';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { DeliveryLeadTimeChip } from '@/components/quotes/delivery-leadtime-chip';
import { MoneyCell } from '@/components/quotes/money-cell';
import { QuoteStatusBadge } from '@/components/quotes/quote-status-badge';
import { SortSelect } from '@/components/sort-select';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useFormatting } from '@/contexts/formatting-context';
import {
    useSupplierQuotes,
    type SupplierQuoteSort,
} from '@/hooks/api/quotes/use-supplier-quotes';
import type { Quote, QuoteStatusEnum } from '@/sdk';

const STATUS_FILTER_OPTIONS: FilterConfig['options'] = [
    { label: 'All statuses', value: '' },
    { label: 'Draft', value: 'draft' },
    { label: 'Submitted', value: 'submitted' },
    { label: 'Awarded', value: 'awarded' },
    { label: 'Withdrawn', value: 'withdrawn' },
    { label: 'Expired', value: 'expired' },
    { label: 'Lost', value: 'lost' },
];

const SORT_OPTIONS = [
    { label: 'Recently updated', value: 'submitted_at' },
    { label: 'Newest created', value: 'created_at' },
    { label: 'Lowest total', value: 'total_minor' },
];

const DEFAULT_PER_PAGE = 10;

export function SupplierQuoteListPage() {
    const { formatDate } = useFormatting();
    const [statusFilter, setStatusFilter] = useState('');
    const [sortBy, setSortBy] = useState<SupplierQuoteSort>('submitted_at');
    const [page, setPage] = useState(1);
    const [searchInput, setSearchInput] = useState('');
    const [searchTerm, setSearchTerm] = useState('');

    useEffect(() => {
        const handle = window.setTimeout(
            () => setSearchTerm(searchInput.trim()),
            250,
        );
        return () => window.clearTimeout(handle);
    }, [searchInput]);

    const filters = useMemo(
        () => ({
            status: (statusFilter as QuoteStatusEnum) || undefined,
            rfqNumber: searchTerm || undefined,
            page,
            perPage: DEFAULT_PER_PAGE,
            sort: sortBy,
        }),
        [page, searchTerm, sortBy, statusFilter],
    );

    const quotesQuery = useSupplierQuotes(filters);
    const quotes = quotesQuery.data?.items ?? [];
    const totalCount = quotesQuery.data?.total ?? quotes.length;
    const paginationMeta = quotesQuery.data?.meta;

    const paginationForComponent = paginationMeta
        ? {
              total: paginationMeta.total ?? totalCount,
              per_page: paginationMeta.perPage ?? DEFAULT_PER_PAGE,
              current_page: paginationMeta.currentPage ?? page,
              last_page:
                  paginationMeta.lastPage ??
                  Math.max(
                      1,
                      Math.ceil(
                          (paginationMeta.total ?? totalCount) /
                              (paginationMeta.perPage ?? DEFAULT_PER_PAGE),
                      ),
                  ),
          }
        : null;

    const filterConfigs = useMemo<FilterConfig[]>(
        () => [
            {
                id: 'status',
                label: 'Status',
                options: STATUS_FILTER_OPTIONS,
                value: statusFilter,
            },
        ],
        [statusFilter],
    );

    const handleFilterChange = (id: string, value: string) => {
        if (id === 'status') {
            setStatusFilter(value);
        }
        setPage(1);
    };

    const handleResetFilters = () => {
        setStatusFilter('');
        setSearchInput('');
        setSearchTerm('');
        setSortBy('submitted_at');
        setPage(1);
    };

    const columns = useMemo<DataTableColumn<Quote>[]>(
        () => [
            {
                key: 'rfq',
                title: 'RFQ',
                render: (quote) => `RFQ #${quote.rfqId}`,
            },
            {
                key: 'quote',
                title: 'Quote',
                render: (quote) => `Quote ${quote.id}`,
            },
            {
                key: 'status',
                title: 'Status',
                render: (quote) => <QuoteStatusBadge status={quote.status} />,
            },
            {
                key: 'total',
                title: 'Total',
                render: (quote) => (
                    <MoneyCell
                        amountMinor={quote.totalMinor}
                        currency={quote.currency}
                        label="Quote total"
                    />
                ),
            },
            {
                key: 'lead_time',
                title: 'Lead time',
                render: (quote) => (
                    <DeliveryLeadTimeChip leadTimeDays={quote.leadTimeDays} />
                ),
            },
            {
                key: 'submitted_at',
                title: 'Submitted',
                align: 'right',
                render: (quote) =>
                    formatDate(quote.submittedAt, {
                        dateStyle: 'short',
                        timeStyle: 'short',
                    }) ?? '—',
            },
            {
                key: 'actions',
                title: 'Actions',
                align: 'right',
                render: (quote) => (
                    <Button asChild variant="ghost" size="sm">
                        <Link to={`/app/supplier/quotes/${quote.id}`}>
                            View
                        </Link>
                    </Button>
                ),
            },
        ],
        [formatDate],
    );

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Supplier Quotes</title>
            </Helmet>

            <PlanUpgradeBanner />

            <div className="space-y-1">
                <p className="text-xs tracking-wide text-muted-foreground uppercase">
                    Supplier workspace
                </p>
                <h1 className="text-2xl font-semibold text-foreground">
                    My Quotes
                </h1>
                <p className="text-sm text-muted-foreground">
                    Track every draft or submitted quote across RFQs and jump
                    back into any record when the buyer requests updates.
                </p>
            </div>

            <FilterBar
                filters={filterConfigs}
                searchPlaceholder="Search RFQ number"
                searchValue={searchInput}
                onSearchChange={(value) => {
                    setSearchInput(value);
                    setPage(1);
                }}
                isLoading={quotesQuery.isLoading && quotes.length === 0}
                onFilterChange={handleFilterChange}
                onReset={handleResetFilters}
            />

            <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-sidebar-border/60 bg-card/60 p-4">
                <SortSelect
                    id="supplier-quotes-sort"
                    label="Sort"
                    value={sortBy}
                    options={SORT_OPTIONS}
                    onChange={(value) => {
                        setSortBy(value as SupplierQuoteSort);
                        setPage(1);
                    }}
                />
                <div className="text-sm text-muted-foreground">
                    Showing {quotes.length} of {totalCount} quotes
                </div>
            </div>

            {quotesQuery.isError ? (
                <Alert variant="destructive">
                    <AlertTitle>Unable to load quotes</AlertTitle>
                    <AlertDescription>
                        We hit an issue retrieving your submissions. Please try
                        again shortly.
                    </AlertDescription>
                </Alert>
            ) : null}

            <DataTable
                data={quotes}
                columns={columns}
                isLoading={quotesQuery.isLoading && quotes.length === 0}
                skeletonRowCount={5}
                emptyState={
                    <EmptyState
                        title="No quotes yet"
                        description="Start from an RFQ invitation to create your first quote. Drafts will appear here automatically."
                        icon={
                            <FileText className="h-10 w-10 text-muted-foreground" />
                        }
                    />
                }
            />

            <Pagination
                meta={paginationForComponent}
                onPageChange={setPage}
                isLoading={quotesQuery.isFetching}
            />
        </div>
    );
}
