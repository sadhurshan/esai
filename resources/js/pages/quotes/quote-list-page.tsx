import { useCallback, useEffect, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { FilterBar, type FilterConfig } from '@/components/filter-bar';
import { DataTable, type DataTableColumn } from '@/components/data-table';
import { SortSelect } from '@/components/sort-select';
import { EmptyState } from '@/components/empty-state';
import { Pagination } from '@/components/pagination';
import { QuoteStatusBadge } from '@/components/quotes/quote-status-badge';
import { MoneyCell } from '@/components/quotes/money-cell';
import { DeliveryLeadTimeChip } from '@/components/quotes/delivery-leadtime-chip';
import { QuoteCompareTable } from '@/components/quotes/quote-compare-table';
import { useAuth } from '@/contexts/auth-context';
import { useRfq } from '@/hooks/api/rfqs/use-rfq';
import { useQuotes, type QuoteListSort, type UseQuotesFilters } from '@/hooks/api/quotes/use-quotes';
import type { Quote, QuoteStatusEnum } from '@/sdk';
import { useMoneySettings } from '@/hooks/api/use-money-settings';
import { FileText, Scale, Star } from 'lucide-react';

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
    const [searchParams] = useSearchParams();
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
    const [shortlistedQuoteIds, setShortlistedQuoteIds] = useState<Set<string>>(new Set());
    const [compareOpen, setCompareOpen] = useState(false);

    const { data: moneySettings } = useMoneySettings();
    const companyMinorUnit = moneySettings?.pricingCurrency?.minorUnit ?? moneySettings?.baseCurrency?.minorUnit ?? DEFAULT_MINOR_UNIT;

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

    const quotes = useMemo(() => quotesQuery.data?.items ?? [], [quotesQuery.data?.items]);

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

    const toggleShortlist = useCallback((quoteId: string) => {
        setShortlistedQuoteIds((current) => {
            const next = new Set(current);
            if (next.has(quoteId)) {
                next.delete(quoteId);
            } else {
                next.add(quoteId);
            }
            return next;
        });
    }, []);

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
                        {shortlistedQuoteIds.has(quote.id) ? (
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
                render: (quote) => formatDateTime(quote.submittedAt),
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
                    const shortlisted = shortlistedQuoteIds.has(quote.id);
                    return (
                        <div className="flex flex-wrap items-center justify-end gap-2">
                            <Button
                                type="button"
                                variant={shortlisted ? 'secondary' : 'outline'}
                                size="sm"
                                onClick={() => toggleShortlist(quote.id)}
                            >
                                <Star className={shortlisted ? 'h-3.5 w-3.5 fill-current' : 'h-3.5 w-3.5'} />
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
    }, [selectedQuoteIds, shortlistedQuoteIds, toggleSelection, toggleShortlist]);

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
                    ctaProps={{ onClick: () => navigate('/app/settings?tab=billing') }}
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
                <EmptyState
                    title="Select an RFQ"
                    description="Open the Quotes view from an RFQ detail page to review supplier submissions."
                    icon={<FileText className="h-10 w-10 text-muted-foreground" />}
                    ctaLabel="Browse RFQs"
                    ctaProps={{ onClick: () => navigate('/app/rfqs') }}
                />
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
                    <p className="text-base font-semibold text-foreground">{formatDateTime(rfq?.deadlineAt)}</p>
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
                rfqItems={rfq?.items}
                shortlistedQuoteIds={shortlistedQuoteIds}
            />
        </div>
    );
}

function formatDateTime(value?: Date | string | null): string {
    if (!value) {
        return '—';
    }

    const date = typeof value === 'string' ? new Date(value) : value;
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function getSupplierName(quote: Quote): string {
    return quote.supplier?.name ?? `Supplier #${quote.supplierId}`;
}
