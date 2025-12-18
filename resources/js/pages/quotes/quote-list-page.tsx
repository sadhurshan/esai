import { useCallback, useEffect, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { FilterBar, type FilterConfig } from '@/components/filter-bar';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { SortSelect } from '@/components/sort-select';
import { EmptyState } from '@/components/empty-state';
import { Pagination } from '@/components/pagination';
import { RfqStatusBadge } from '@/components/rfqs/rfq-status-badge';
import { QuoteStatusBadge } from '@/components/quotes/quote-status-badge';
import { MoneyCell } from '@/components/quotes/money-cell';
import { DeliveryLeadTimeChip } from '@/components/quotes/delivery-leadtime-chip';
import { QuoteCompareTable } from '@/components/quotes/quote-compare-table';
import { Skeleton } from '@/components/ui/skeleton';
import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { useRfq } from '@/hooks/api/rfqs/use-rfq';
import { useQuotes, type QuoteListSort, type UseQuotesFilters } from '@/hooks/api/quotes/use-quotes';
import { useQuoteShortlistMutation } from '@/hooks/api/quotes/use-shortlist-mutation';
import { useRfqs, type RfqStatusFilter } from '@/hooks/api/use-rfqs';
import type { Quote, QuoteStatusEnum, Rfq } from '@/sdk';
import { useMoneySettings } from '@/hooks/api/use-money-settings';
import { FileText, Files, Scale, Star, Loader2 } from 'lucide-react';

const STATUS_FILTER_OPTIONS: FilterConfig['options'] = [
    { label: 'All statuses', value: '' },
    { label: 'Submitted', value: 'submitted' },
    { label: 'Withdrawn', value: 'withdrawn' },
    { label: 'Expired', value: 'expired' },
    { label: 'Awarded', value: 'awarded' },
];

const SORT_OPTIONS = [
    { label: 'Newest submitted', value: 'submitted_at' },
    { label: 'Fastest lead time', value: 'lead_time_days' },
    { label: 'Lowest total', value: 'total_minor' },
];

const DEFAULT_PER_PAGE = 10;
const DEFAULT_MINOR_UNIT = 2;

export function QuoteListPage() {
    const params = useParams<{ rfqId?: string }>();
    const [searchParams, setSearchParams] = useSearchParams();
    const navigate = useNavigate();
    const rfqIdFromRoute = params.rfqId ?? searchParams.get('rfqId') ?? searchParams.get('rfq_id');
    const rfqId = rfqIdFromRoute ?? null;

    const { hasFeature, state } = useAuth();
    const featureFlagsLoaded = state.status !== 'idle' && state.status !== 'loading';
    const quotesFeatureEnabled = hasFeature('quotes_enabled');
    const canAccessQuotes = !featureFlagsLoaded || quotesFeatureEnabled;

    const [page, setPage] = useState(1);
    const [supplierFilter, setSupplierFilter] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [priceMinInput, setPriceMinInput] = useState('');
    const [priceMaxInput, setPriceMaxInput] = useState('');
    const [leadMinInput, setLeadMinInput] = useState('');
    const [leadMaxInput, setLeadMaxInput] = useState('');
    const [searchInput, setSearchInput] = useState('');
    const [searchTerm, setSearchTerm] = useState('');
    const [sortBy, setSortBy] = useState<QuoteListSort>('submitted_at');
    const [selectedQuoteIds, setSelectedQuoteIds] = useState<Set<string>>(new Set());
    const [compareOpen, setCompareOpen] = useState(false);
    const [shortlistTargetId, setShortlistTargetId] = useState<string | null>(null);

    const { data: moneySettings } = useMoneySettings();
    const companyMinorUnit = moneySettings?.pricingCurrency?.minorUnit ?? moneySettings?.baseCurrency?.minorUnit ?? DEFAULT_MINOR_UNIT;
    const { formatDate } = useFormatting();

    useEffect(() => {
        const handle = window.setTimeout(() => setSearchTerm(searchInput.trim().toLowerCase()), 250);
        return () => window.clearTimeout(handle);
    }, [searchInput]);

    const priceRangeMinor = useMemo(() => {
        const min = parseFloat(priceMinInput);
        const max = parseFloat(priceMaxInput);
        if (Number.isNaN(min) && Number.isNaN(max)) {
            return undefined;
        }
        const factor = Math.pow(10, companyMinorUnit);
        return {
            min: Number.isNaN(min) ? undefined : Math.round(min * factor),
            max: Number.isNaN(max) ? undefined : Math.round(max * factor),
        };
    }, [companyMinorUnit, priceMaxInput, priceMinInput]);

    const leadTimeRangeDays = useMemo(() => {
        const min = parseInt(leadMinInput, 10);
        const max = parseInt(leadMaxInput, 10);
        if (Number.isNaN(min) && Number.isNaN(max)) {
            return undefined;
        }
        return {
            min: Number.isNaN(min) ? undefined : min,
            max: Number.isNaN(max) ? undefined : max,
        };
    }, [leadMaxInput, leadMinInput]);

    const quoteFilters = useMemo<UseQuotesFilters>(() => ({
        supplierId: supplierFilter || undefined,
        status: (statusFilter as QuoteStatusEnum) || undefined,
        priceRangeMinor,
        leadTimeRangeDays,
        page,
        perPage: DEFAULT_PER_PAGE,
        sort: sortBy,
    }), [leadTimeRangeDays, page, priceRangeMinor, sortBy, statusFilter, supplierFilter]);

    const rfqQuery = useRfq(rfqId, { enabled: Boolean(rfqId) });
    const quotesQuery = useQuotes(rfqId ?? undefined, quoteFilters, { enabled: Boolean(rfqId) && canAccessQuotes });
    const shortlistMutation = useQuoteShortlistMutation();

    const quotes = useMemo(() => quotesQuery.data?.items ?? [], [quotesQuery.data?.items]);
    const shortlistedQuoteIds = useMemo(() => {
        const ids = new Set<string>();
        quotes.forEach((quote) => {
            if (quote.isShortlisted) {
                ids.add(quote.id);
            }
        });
        return ids;
    }, [quotes]);

    const searchFilteredQuotes = useMemo(() => {
        if (!searchTerm) {
            return quotes;
        }

        return quotes.filter((quote) => {
            const supplierName = (quote.supplier?.name ?? `Supplier #${quote.supplierId}`).toLowerCase();
            const note = quote.note?.toLowerCase() ?? '';
            return supplierName.includes(searchTerm) || note.includes(searchTerm);
        });
    }, [quotes, searchTerm]);

    const sortedQuotes = useMemo(() => {
        const list = [...searchFilteredQuotes];

        switch (sortBy) {
            case 'lead_time_days':
                list.sort((a, b) => (a.leadTimeDays ?? Number.MAX_SAFE_INTEGER) - (b.leadTimeDays ?? Number.MAX_SAFE_INTEGER));
                break;
            case 'total_minor':
                list.sort((a, b) => a.totalMinor - b.totalMinor);
                break;
            default:
                list.sort((a, b) => {
                    const aTime = a.submittedAt ? a.submittedAt.getTime() : 0;
                    const bTime = b.submittedAt ? b.submittedAt.getTime() : 0;
                    return bTime - aTime;
                });
        }

        return list;
    }, [searchFilteredQuotes, sortBy]);

    const supplierOptions = useMemo(() => {
        const optionMap = new Map<string, string>();

        quotes.forEach((quote) => {
            optionMap.set(String(quote.supplierId), quote.supplier?.name ?? `Supplier #${quote.supplierId}`);
        });

        const dynamicOptions = Array.from(optionMap.entries())
            .sort((a, b) => a[1].localeCompare(b[1]))
            .map(([value, label]) => ({ label, value }));

        return [{ label: 'All suppliers', value: '' }, ...dynamicOptions];
    }, [quotes]);

    const filterConfigs: FilterConfig[] = [
        { id: 'supplier', label: 'Supplier', options: supplierOptions, value: supplierFilter },
        { id: 'status', label: 'Status', options: STATUS_FILTER_OPTIONS, value: statusFilter },
    ];

    const handleFilterChange = (id: string, value: string) => {
        if (id === 'supplier') {
            setSupplierFilter(value);
        } else if (id === 'status') {
            setStatusFilter(value);
        }
        setPage(1);
    };

    const handleResetFilters = () => {
        setSupplierFilter('');
        setStatusFilter('');
        setPriceMinInput('');
        setPriceMaxInput('');
        setLeadMinInput('');
        setLeadMaxInput('');
        setSearchInput('');
        setSearchTerm('');
        setSortBy('submitted_at');
        setPage(1);
    };

    const handleRfqSelection = useCallback(
        (nextRfqId: string) => {
            if (!nextRfqId) {
                return;
            }

            if (params.rfqId) {
                navigate(`/app/rfqs/${nextRfqId}/quotes`);
                return;
            }

            const nextParams = new URLSearchParams(searchParams);
            nextParams.set('rfqId', nextRfqId);
            setSearchParams(nextParams, { replace: false });
        },
        [navigate, params.rfqId, searchParams, setSearchParams],
    );

    const handlePriceMinChange = (value: string) => {
        setPriceMinInput(value);
        setPage(1);
    };

    const handlePriceMaxChange = (value: string) => {
        setPriceMaxInput(value);
        setPage(1);
    };

    const handleLeadMinChange = (value: string) => {
        setLeadMinInput(value);
        setPage(1);
    };

    const handleLeadMaxChange = (value: string) => {
        setLeadMaxInput(value);
        setPage(1);
    };

    const handleSearchInputChange = (value: string) => {
        setSearchInput(value);
        setPage(1);
    };

    const toggleSelection = useCallback((quoteId: string, checked: boolean) => {
        setSelectedQuoteIds((current) => {
            const next = new Set(current);
            if (checked) {
                next.add(quoteId);
            } else {
                next.delete(quoteId);
            }
            return next;
        });
    }, []);

    const handleShortlistToggle = useCallback(
        (quote: Quote) => {
            if (shortlistMutation.isPending) {
                return;
            }

            const targetId = quote.id;
            const shouldShortlist = !quote.isShortlisted;
            setShortlistTargetId(targetId);

            shortlistMutation.mutate(
                { quoteId: quote.id, shortlist: shouldShortlist, rfqId: quote.rfqId },
                {
                    onSuccess: (updated) => {
                        publishToast({
                            variant: 'success',
                            title: shouldShortlist ? 'Quote shortlisted' : 'Shortlist updated',
                            description: shouldShortlist
                                ? `${getSupplierName(updated)} is now on your shortlist.`
                                : `${getSupplierName(updated)} was removed from your shortlist.`,
                        });
                        if (typeof window !== 'undefined') {
                            window.location.reload();
                        }
                    },
                    onError: (error) => {
                        publishToast({
                            variant: 'destructive',
                            title: 'Unable to update shortlist',
                            description:
                                error instanceof Error
                                    ? error.message
                                    : 'Please check your connection and try again.',
                        });
                    },
                    onSettled: () => {
                        setShortlistTargetId((current) => (current === targetId ? null : current));
                    },
                },
            );
        },
        [shortlistMutation],
    );

    const selectedQuotes = useMemo(() => {
        return quotes.filter((quote) => selectedQuoteIds.has(quote.id));
    }, [quotes, selectedQuoteIds]);

    const paginationMeta = quotesQuery.data?.meta?.pagination;
    const paginationForComponent = paginationMeta
        ? {
              total: paginationMeta.total,
              per_page: paginationMeta.perPage,
              current_page: paginationMeta.currentPage,
              last_page: paginationMeta.lastPage,
          }
        : null;

    const tableColumns: DataTableColumn<Quote>[] = useMemo(() => {
        return [
            {
                key: 'select',
                title: '',
                width: '40px',
                render: (quote) => (
                    <Checkbox
                        checked={selectedQuoteIds.has(quote.id)}
                        onCheckedChange={(checked) => toggleSelection(quote.id, Boolean(checked))}
                        aria-label={`Select quote from ${getSupplierName(quote)}`}
                    />
                ),
            },
            {
                key: 'supplier',
                title: 'Supplier',
                render: (quote) => (
                    <div className="flex flex-col gap-1">
                        <span className="font-semibold text-foreground">{getSupplierName(quote)}</span>
                        {quote.isShortlisted ? (
                            <span className="flex items-center gap-1 text-xs font-medium text-emerald-600">
                                <Star className="h-3.5 w-3.5 fill-current" />
                                Shortlisted
                            </span>
                        ) : null}
                        {quote.note ? (
                            <span className="text-xs text-muted-foreground">{quote.note}</span>
                        ) : null}
                    </div>
                ),
            },
            {
                key: 'total',
                title: 'Total',
                align: 'right',
                render: (quote) => <MoneyCell amountMinor={quote.totalMinor} currency={quote.currency} label="Quote total" />,
            },
            {
                key: 'currency',
                title: 'Currency',
                render: (quote) => quote.currency,
            },
            {
                key: 'lead_time',
                title: 'Lead time',
                render: (quote) => <DeliveryLeadTimeChip leadTimeDays={quote.leadTimeDays} />,
            },
            {
                key: 'submitted_at',
                title: 'Submitted at',
                render: (quote) =>
                    formatDate(quote.submittedAt, {
                        dateStyle: 'medium',
                        timeStyle: 'short',
                    }),
            },
            {
                key: 'revision',
                title: 'Revisions',
                render: (quote) => `Rev ${quote.revisionNo ?? 1}`,
            },
            {
                key: 'status',
                title: 'Status',
                render: (quote) => <QuoteStatusBadge status={quote.status} />,
            },
            {
                key: 'actions',
                title: 'Actions',
                align: 'right',
                render: (quote) => {
                    const shortlisted = Boolean(quote.isShortlisted);
                    const isUpdatingShortlist = shortlistMutation.isPending && shortlistTargetId === quote.id;
                    return (
                        <div className="flex flex-wrap items-center justify-end gap-2">
                            <Button
                                type="button"
                                variant={shortlisted ? 'secondary' : 'outline'}
                                size="sm"
                                disabled={isUpdatingShortlist}
                                onClick={() => handleShortlistToggle(quote)}
                            >
                                {isUpdatingShortlist ? (
                                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                ) : (
                                    <Star className={shortlisted ? 'h-3.5 w-3.5 fill-current' : 'h-3.5 w-3.5'} />
                                )}
                                {shortlisted ? 'Shortlisted' : 'Shortlist'}
                            </Button>
                            <Button asChild variant="ghost" size="sm">
                                <Link to={`/app/quotes/${quote.id}`}>View</Link>
                            </Button>
                        </div>
                    );
                },
            },
        ];
    }, [
        formatDate,
        handleShortlistToggle,
        selectedQuoteIds,
        shortlistedQuoteIds,
        shortlistMutation.isPending,
        shortlistTargetId,
        toggleSelection,
    ]);

    if (featureFlagsLoaded && !quotesFeatureEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Quotes</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="Quotes unavailable on current plan"
                    description="Upgrade your workspace plan to unlock supplier quote workflows."
                    icon={<FileText className="h-10 w-10 text-muted-foreground" />}
                    ctaLabel="View plans"
                    ctaProps={{ onClick: () => navigate('/app/settings/billing') }}
                />
            </div>
        );
    }

    if (!rfqId) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Quotes</title>
                </Helmet>
                <PlanUpgradeBanner />
                <QuoteRfqPicker onSelect={handleRfqSelection} />
            </div>
        );
    }

    const rfq = rfqQuery.data;

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Quotes · RFQ {rfq?.number ?? rfqId}</title>
            </Helmet>

            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">RFQ {rfq?.number ?? rfqId}</p>
                    <h1 className="text-2xl font-semibold text-foreground">Supplier Quotes</h1>
                    <p className="text-sm text-muted-foreground">
                        Compare supplier submissions, shortlist candidates, and open the comparison drawer to evaluate pricing.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="outline" size="sm" onClick={() => navigate(`/app/rfqs/${rfqId}`)}>
                        Back to RFQ
                    </Button>
                </div>
            </div>

            <div className="grid gap-4 rounded-2xl border border-sidebar-border/60 bg-card/60 p-4 text-sm sm:grid-cols-3">
                <div className="space-y-1">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Status</p>
                    <p className="text-base font-semibold text-foreground">{rfq?.status ?? '—'}</p>
                </div>
                <div className="space-y-1">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Due date</p>
                    <p className="text-base font-semibold text-foreground">
                        {formatDate(rfq?.deadlineAt, {
                            dateStyle: 'medium',
                            timeStyle: 'short',
                        })}
                    </p>
                </div>
                <div className="space-y-1">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Lines</p>
                    <p className="text-base font-semibold text-foreground">{rfq?.items?.length ?? 0}</p>
                </div>
            </div>

            <FilterBar
                filters={filterConfigs}
                searchPlaceholder="Search supplier or notes"
                searchValue={searchInput}
                onSearchChange={handleSearchInputChange}
                isLoading={quotesQuery.isLoading && quotes.length === 0}
                onFilterChange={handleFilterChange}
                onReset={handleResetFilters}
            />

            <div className="grid gap-4 rounded-2xl border border-sidebar-border/60 bg-background/60 p-4 md:grid-cols-3">
                <div className="space-y-2">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Price range ({moneySettings?.pricingCurrency?.code ?? moneySettings?.baseCurrency?.code ?? 'USD'})</p>
                    <div className="flex items-center gap-2">
                        <Input
                            type="number"
                            inputMode="decimal"
                            value={priceMinInput}
                            onChange={(event) => handlePriceMinChange(event.target.value)}
                            placeholder="Min"
                        />
                        <span className="text-sm text-muted-foreground">to</span>
                        <Input
                            type="number"
                            inputMode="decimal"
                            value={priceMaxInput}
                            onChange={(event) => handlePriceMaxChange(event.target.value)}
                            placeholder="Max"
                        />
                    </div>
                </div>
                <div className="space-y-2">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Lead time (days)</p>
                    <div className="flex items-center gap-2">
                        <Input
                            type="number"
                            value={leadMinInput}
                            onChange={(event) => handleLeadMinChange(event.target.value)}
                            placeholder="Min"
                        />
                        <span className="text-sm text-muted-foreground">to</span>
                        <Input
                            type="number"
                            value={leadMaxInput}
                            onChange={(event) => handleLeadMaxChange(event.target.value)}
                            placeholder="Max"
                        />
                    </div>
                </div>
                <div className="flex flex-col gap-3">
                    <SortSelect
                        id="quotes-sort"
                        label="Sort"
                        value={sortBy}
                        options={SORT_OPTIONS}
                        onChange={(value) => {
                            setSortBy(value as QuoteListSort);
                            setPage(1);
                        }}
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onClick={() => setSelectedQuoteIds(new Set<string>())}
                            disabled={selectedQuotes.length === 0}
                        >
                            Clear selection
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => setCompareOpen(true)}
                            disabled={selectedQuotes.length < 2}
                        >
                            <Scale className="h-4 w-4" /> Compare ({selectedQuotes.length})
                        </Button>
                    </div>
                </div>
            </div>

            {quotesQuery.isError ? (
                <Alert variant="destructive">
                    <AlertTitle>Unable to load quotes</AlertTitle>
                    <AlertDescription>
                        We ran into an issue loading quotes for this RFQ. Please refresh or try again later.
                    </AlertDescription>
                </Alert>
            ) : null}

            <DataTable
                data={sortedQuotes}
                columns={tableColumns}
                isLoading={quotesQuery.isLoading && sortedQuotes.length === 0}
                skeletonRowCount={5}
                emptyState={
                    <EmptyState
                        title="No quotes yet"
                        description="Suppliers have not submitted quotes for this RFQ. Share the event or wait for responses."
                        icon={<FileText className="h-10 w-10 text-muted-foreground" />}
                        ctaLabel="View RFQ"
                        ctaProps={{ onClick: () => navigate(`/app/rfqs/${rfqId}`) }}
                    />
                }
            />

            <Pagination meta={paginationForComponent} onPageChange={setPage} isLoading={quotesQuery.isFetching} />

            <QuoteCompareTable
                open={compareOpen}
                onOpenChange={setCompareOpen}
                quotes={selectedQuotes}
                rfqId={rfqId}
                rfqItems={rfq?.items}
                shortlistedQuoteIds={shortlistedQuoteIds}
                selectedQuoteIds={selectedQuoteIds}
            />
        </div>
    );
}

function getSupplierName(quote: Quote): string {
    return quote.supplier?.name ?? `Supplier #${quote.supplierId}`;
}

function resolveRfqTitle(rfq: Rfq): string {
    const title = (rfq as { title?: string | null }).title;
    if (typeof title === 'string' && title.trim().length > 0) {
        return title.trim();
    }
    const itemName = (rfq as { itemName?: string | null }).itemName;
    if (typeof itemName === 'string' && itemName.trim().length > 0) {
        return itemName.trim();
    }
    return `RFQ #${rfq.number ?? rfq.id}`;
}

function resolveRfqSubtitle(rfq: Rfq): string {
    const method = (rfq as { method?: string | null }).method;
    const material = (rfq as { material?: string | null }).material;
    const methodLabel = method && method.trim().length > 0 ? method : '—';
    const materialLabel = material && material.trim().length > 0 ? material : '—';
    return `${methodLabel} · ${materialLabel}`;
}

function resolveRfqQuantity(rfq: Rfq): number | null {
    const extended = rfq as Rfq & {
        quantityTotal?: number | null;
        quantity?: number | null;
        items?: Array<{ quantity?: number | string | null }>;
    };

    if (typeof extended.quantityTotal === 'number') {
        return extended.quantityTotal;
    }

    if (typeof extended.quantity === 'number') {
        return extended.quantity;
    }

    if (extended.items && extended.items.length > 0) {
        const total = extended.items.reduce((sum, item) => {
            const value = typeof item.quantity === 'number' ? item.quantity : Number(item.quantity);
            return Number.isFinite(value) ? sum + Number(value) : sum;
        }, 0);

        return total > 0 ? total : null;
    }

    return null;
}

interface QuoteRfqPickerProps {
    onSelect: (rfqId: string) => void;
}

type QuotePickerBiddingFilter = 'all' | 'open' | 'private';

const RFQ_PICKER_STATUS_OPTIONS: Array<{ value: RfqStatusFilter; label: string }> = [
    { value: 'all', label: 'All statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'open', label: 'Open' },
    { value: 'closed', label: 'Closed' },
    { value: 'awarded', label: 'Awarded' },
    { value: 'cancelled', label: 'Cancelled' },
];

const RFQ_PICKER_BIDDING_OPTIONS: Array<{ value: QuotePickerBiddingFilter; label: string }> = [
    { value: 'all', label: 'All bidding modes' },
    { value: 'open', label: 'Open bidding only' },
    { value: 'private', label: 'Private invitations only' },
];

function QuoteRfqPicker({ onSelect }: QuoteRfqPickerProps) {
    const { formatDate, formatNumber } = useFormatting();
    const [searchInput, setSearchInput] = useState('');
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState<RfqStatusFilter>('all');
    const [biddingFilter, setBiddingFilter] = useState<QuotePickerBiddingFilter>('all');
    const [dateFrom, setDateFrom] = useState<string | undefined>();
    const [dateTo, setDateTo] = useState<string | undefined>();
    const [cursor, setCursor] = useState<string | undefined>();
    const perPage = 10;

    useEffect(() => {
        const handle = window.setTimeout(() => setSearchTerm(searchInput.trim()), 250);
        return () => window.clearTimeout(handle);
    }, [searchInput]);

    const rfqsQuery = useRfqs({
        perPage,
        status: statusFilter,
        search: searchTerm,
        dueFrom: dateFrom,
        dueTo: dateTo,
        cursor,
        openBidding: biddingFilter === 'all' ? undefined : biddingFilter === 'open',
    });

    const items = rfqsQuery.items ?? [];
    const cursorMeta = rfqsQuery.cursor;
    const canGoPrev = Boolean(cursorMeta?.prevCursor);
    const canGoNext = Boolean(cursorMeta?.nextCursor);
    const showSkeleton = rfqsQuery.isLoading && items.length === 0;
    const showEmptyState = !showSkeleton && !rfqsQuery.isError && items.length === 0;

    const handleSelect = (rfq: Rfq) => {
        if (!rfq?.id) {
            return;
        }
        onSelect(String(rfq.id));
    };

    const handleReset = () => {
        setStatusFilter('all');
        setBiddingFilter('all');
        setDateFrom(undefined);
        setDateTo(undefined);
        setSearchInput('');
        setSearchTerm('');
        setCursor(undefined);
    };

    const derivedRows = showSkeleton
        ? Array.from({ length: 5 }).map((_, index) => (
              <tr key={`rfq-picker-skeleton-${index}`} className="hover:bg-muted/30">
                  <td className="px-4 py-4">
                      <Skeleton className="h-4 w-20" />
                  </td>
                  <td className="px-4 py-4">
                      <div className="space-y-2">
                          <Skeleton className="h-4 w-48" />
                          <Skeleton className="h-3 w-32" />
                      </div>
                  </td>
                  <td className="px-4 py-4">
                      <Skeleton className="h-4 w-24" />
                  </td>
                  <td className="px-4 py-4">
                      <Skeleton className="h-4 w-24" />
                  </td>
                  <td className="px-4 py-4 text-right">
                      <Skeleton className="ml-auto h-5 w-16" />
                  </td>
                  <td className="px-4 py-4 text-right">
                      <Skeleton className="ml-auto h-8 w-24" />
                  </td>
              </tr>
          ))
        : items.map((rfq) => {
              const quantityTotal = resolveRfqQuantity(rfq);
              const dueAt = (rfq as { dueAt?: string | null }).dueAt ?? (rfq as { deadlineAt?: string | null }).deadlineAt;
              const publishAt = (rfq as { publishAt?: string | null }).publishAt ?? (rfq as { sentAt?: string | null }).sentAt;
              return (
                  <tr key={rfq.id} className="hover:bg-muted/30">
                      <td className="px-4 py-4 align-top">
                          <div className="flex flex-col text-sm">
                              <span className="font-semibold text-foreground">{resolveRfqTitle(rfq)}</span>
                              <span className="text-xs text-muted-foreground">RFQ #{rfq.number ?? rfq.id}</span>
                          </div>
                      </td>
                      <td className="px-4 py-4 align-top text-sm text-muted-foreground">
                          <div className="flex flex-col">
                              <span>{resolveRfqSubtitle(rfq)}</span>
                              {(rfq as { deliveryLocation?: string | null }).deliveryLocation ? (
                                  <span>{(rfq as { deliveryLocation?: string | null }).deliveryLocation}</span>
                              ) : null}
                          </div>
                      </td>
                      <td className="px-4 py-4 align-top text-sm text-muted-foreground">
                          {quantityTotal !== null ? formatNumber(quantityTotal) : '—'}
                      </td>
                      <td className="px-4 py-4 align-top text-xs text-muted-foreground">
                          <div className="flex flex-col">
                              <span className="font-medium text-foreground">{formatDate(publishAt)}</span>
                              <span>Due {formatDate(dueAt)}</span>
                          </div>
                      </td>
                      <td className="px-4 py-4 align-top text-right">
                          <RfqStatusBadge status={rfq.status ?? undefined} />
                      </td>
                      <td className="px-4 py-4 align-top text-right">
                          <Button size="sm" variant="outline" onClick={() => handleSelect(rfq)}>
                              Review quotes
                          </Button>
                      </td>
                  </tr>
              );
          });

    return (
        <div className="w-full">
            <div className="space-y-1">
                <h1 className="text-2xl font-semibold text-foreground">Select an RFQ</h1>
                <p className="text-sm text-muted-foreground">
                    Use the RFQ table below to open the buyer quote workspace without leaving this page.
                </p>
            </div>

            <div className="mt-6 flex flex-wrap items-end gap-3 rounded-xl border bg-card px-4 py-3 shadow-sm">
                <div className="flex min-w-[220px] flex-1 flex-col gap-1">
                    <label className="text-xs font-medium text-muted-foreground">Search</label>
                    <Input
                        value={searchInput}
                        onChange={(event) => {
                            setSearchInput(event.target.value);
                            setCursor(undefined);
                        }}
                        placeholder="Search RFQs"
                        className="h-9"
                    />
                </div>
                <div className="flex flex-col gap-1">
                    <label className="text-xs font-medium text-muted-foreground">Status</label>
                    <Select
                        value={statusFilter}
                        onValueChange={(value) => {
                            setStatusFilter(value as RfqStatusFilter);
                            setCursor(undefined);
                        }}
                    >
                        <SelectTrigger className="w-40 h-9">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            {RFQ_PICKER_STATUS_OPTIONS.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="flex flex-col gap-1">
                    <label className="text-xs font-medium text-muted-foreground">Bidding</label>
                    <Select
                        value={biddingFilter}
                        onValueChange={(value) => {
                            setBiddingFilter(value as QuotePickerBiddingFilter);
                            setCursor(undefined);
                        }}
                    >
                        <SelectTrigger className="w-48 h-9">
                            <SelectValue placeholder="Bidding" />
                        </SelectTrigger>
                        <SelectContent>
                            {RFQ_PICKER_BIDDING_OPTIONS.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="flex flex-col gap-1">
                    <label className="text-xs font-medium text-muted-foreground">Due from</label>
                    <Input
                        type="date"
                        value={dateFrom ?? ''}
                        onChange={(event) => {
                            setDateFrom(event.target.value || undefined);
                            setCursor(undefined);
                        }}
                        className="h-9"
                    />
                </div>
                <div className="flex flex-col gap-1">
                    <label className="text-xs font-medium text-muted-foreground">Due to</label>
                    <Input
                        type="date"
                        value={dateTo ?? ''}
                        onChange={(event) => {
                            setDateTo(event.target.value || undefined);
                            setCursor(undefined);
                        }}
                        className="h-9"
                    />
                </div>
                <div className="flex flex-col gap-1">
                    <span className="text-xs text-transparent">reset</span>
                    <Button variant="outline" size="sm" onClick={handleReset}>
                        Reset filters
                    </Button>
                </div>
            </div>

            {rfqsQuery.isError ? (
                <Alert variant="destructive" className="mt-4">
                    <AlertTitle>Unable to load RFQs</AlertTitle>
                    <AlertDescription>Refresh or adjust filters and try again.</AlertDescription>
                </Alert>
            ) : null}

            <div className="mt-4 overflow-hidden rounded-xl border bg-card shadow-sm">
                <table className="min-w-full divide-y divide-border text-sm">
                    <thead className="bg-muted/40 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th className="px-4 py-3 text-left font-medium">RFQ</th>
                            <th className="px-4 py-3 text-left font-medium">Summary</th>
                            <th className="px-4 py-3 text-left font-medium">Quantity</th>
                            <th className="px-4 py-3 text-left font-medium">Published / Due</th>
                            <th className="px-4 py-3 text-right font-medium">Status</th>
                            <th className="px-4 py-3 text-right font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">{derivedRows}</tbody>
                </table>

                {showEmptyState ? (
                    <div className="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center">
                        <Files className="h-10 w-10 text-muted-foreground" />
                        <div className="space-y-1">
                            <p className="text-base font-semibold text-foreground">No RFQs match these filters</p>
                            <p className="max-w-sm text-sm text-muted-foreground">
                                Adjust filters or create a new RFQ to start collecting supplier quotes.
                            </p>
                        </div>
                        <Button asChild variant="outline" size="sm">
                            <Link to="/app/rfqs/new">Create RFQ</Link>
                        </Button>
                    </div>
                ) : null}
            </div>

            <div className="mt-4 flex flex-col gap-3 border-t pt-4 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                <span>Showing {items.length} RFQs</span>
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!canGoPrev || rfqsQuery.isFetching}
                        onClick={() => setCursor(cursorMeta?.prevCursor ?? undefined)}
                    >
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!canGoNext || rfqsQuery.isFetching}
                        onClick={() => setCursor(cursorMeta?.nextCursor ?? undefined)}
                    >
                        Next
                    </Button>
                </div>
            </div>

            <div className="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                <span>Need to manage RFQs instead?</span>
                <Button asChild variant="link" size="sm" className="px-0">
                    <Link to="/app/rfqs">Go to RFQ list</Link>
                </Button>
            </div>
        </div>
    );
}
