import { Fragment, useCallback, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowUpDown, Award, Loader2, Star } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { publishToast } from '@/components/ui/use-toast';
import { DeliveryLeadTimeChip } from './delivery-leadtime-chip';
import { MoneyCell } from './money-cell';
import { QuoteStatusBadge } from './quote-status-badge';
import { useFormatting } from '@/contexts/formatting-context';
import { useQuoteComparison } from '@/hooks/api/quotes/use-quote-comparison';
import { useQuoteShortlistMutation } from '@/hooks/api/quotes/use-shortlist-mutation';
import { cn } from '@/lib/utils';
import type { Quote, QuoteItem, RfqItem } from '@/sdk';
import type { QuoteComparisonRow } from '@/types/quotes';

interface QuoteCompareTableProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    quotes: Quote[];
    rfqId?: string | number | null;
    rfqItems?: RfqItem[];
    shortlistedQuoteIds?: Set<string>;
    selectedQuoteIds?: Set<string>;
}

interface LineDefinition {
    id: string;
    lineNo?: number;
    label: string;
    spec?: string;
    quantity?: number;
    uom?: string;
}

type ComparisonSort = 'composite' | 'price' | 'leadTime' | 'risk' | 'fit';
type ScoreFilter = 'all' | 'top3' | 'best';

const EMPTY_COMPARISON_ROWS: QuoteComparisonRow[] = [];

const FALLBACK_ORDER = Number.MAX_SAFE_INTEGER;
const SORT_OPTIONS: { label: string; value: ComparisonSort }[] = [
    { label: 'Best overall score', value: 'composite' },
    { label: 'Price score', value: 'price' },
    { label: 'Lead time score', value: 'leadTime' },
    { label: 'Risk score', value: 'risk' },
    { label: 'Fit score', value: 'fit' },
];

const SCORE_FILTER_OPTIONS: { label: string; value: ScoreFilter; helper?: string }[] = [
    { label: 'All quotes', value: 'all' },
    { label: 'Rank 1-3', value: 'top3', helper: 'Shows the top three suppliers by normalized rank.' },
    { label: 'Composite >= 80%', value: 'best', helper: 'Hide suppliers scoring below 80% overall.' },
];

export function QuoteCompareTable({
    open,
    onOpenChange,
    quotes,
    rfqId,
    rfqItems,
    shortlistedQuoteIds,
    selectedQuoteIds,
}: QuoteCompareTableProps) {
    const { formatNumber, formatDate } = useFormatting();
    const navigate = useNavigate();
    const [sortField, setSortField] = useState<ComparisonSort>('composite');
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');
    const [scoreFilter, setScoreFilter] = useState<ScoreFilter>('all');
    const [supplierQuery, setSupplierQuery] = useState('');
    const [shortlistOnly, setShortlistOnly] = useState(false);
    const [statusFilter, setStatusFilter] = useState<Set<Quote['status']>>(() => new Set());
    const [shortlistTargetId, setShortlistTargetId] = useState<string | null>(null);
    const shortlistMutation = useQuoteShortlistMutation();

    const selection = useMemo(() => {
        if (selectedQuoteIds) {
            return new Set(selectedQuoteIds);
        }
        return new Set(quotes.map((quote) => quote.id));
    }, [quotes, selectedQuoteIds]);

    const selectedQuoteIdList = useMemo(() => Array.from(selection), [selection]);
    const hasSelections = selectedQuoteIdList.length > 0;
    const canLaunchAwardFlow = Boolean(rfqId) && hasSelections;
    const awardRoute = rfqId ? `/app/rfqs/${rfqId}/awards` : null;

    const handleLaunchAwardFlow = () => {
        if (!awardRoute) {
            return;
        }
        navigate(awardRoute, {
            state: {
                quoteIds: selectedQuoteIdList,
                source: 'compare',
            },
        });
    };

    const handleShortlistToggle = useCallback(
        (quote: Quote, rfqContext?: string | number | null) => {
            if (shortlistMutation.isPending) {
                return;
            }

            const targetId = quote.id;
            const shouldShortlist = !quote.isShortlisted;
            setShortlistTargetId(targetId);

            shortlistMutation.mutate(
                { quoteId: quote.id, shortlist: shouldShortlist, rfqId: rfqContext ?? quote.rfqId ?? rfqId },
                {
                    onSuccess: (updated) => {
                        publishToast({
                            variant: 'success',
                            title: shouldShortlist ? 'Quote shortlisted' : 'Shortlist updated',
                            description: shouldShortlist
                                ? `${updated.supplier?.name ?? `Supplier #${updated.supplierId}`} is now on your shortlist.`
                                : `${updated.supplier?.name ?? `Supplier #${updated.supplierId}`} was removed from your shortlist.`,
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
                                error instanceof Error ? error.message : 'Please check your connection and try again.',
                        });
                    },
                    onSettled: () => {
                        setShortlistTargetId((current) => (current === targetId ? null : current));
                    },
                },
            );
        },
        [rfqId, shortlistMutation],
    );

    const comparisonQuery = useQuoteComparison(rfqId ?? undefined, {
        enabled: open && Boolean(rfqId),
    });
    const comparisonRows = useMemo(() => comparisonQuery.data ?? EMPTY_COMPARISON_ROWS, [comparisonQuery.data]);

    const fallbackRows = useMemo(() => quotes.map(buildFallbackRow), [quotes]);
    const comparisonById = useMemo(() => new Map(comparisonRows.map((row) => [row.quote.id, row])), [comparisonRows]);

    const comparisonSelection = useMemo(
        () =>
            Array.from(selection)
                .map((quoteId) => comparisonById.get(quoteId))
                .filter((row): row is QuoteComparisonRow => Boolean(row)),
        [comparisonById, selection],
    );

    const fallbackSelection = useMemo(
        () => fallbackRows.filter((row) => selection.has(row.quote.id)),
        [fallbackRows, selection],
    );

    const candidateRows = comparisonSelection.length > 0 ? comparisonSelection : fallbackSelection;
    const filteredRows = useMemo(
        () =>
            candidateRows.filter((row) => {
                if (shortlistOnly && !shortlistedQuoteIds?.has(row.quote.id)) {
                    return false;
                }

                if (supplierQuery.trim()) {
                    const supplierName = row.quote.supplier?.name ?? '';
                    if (!supplierName.toLowerCase().includes(supplierQuery.trim().toLowerCase())) {
                        return false;
                    }
                }

                if (statusFilter.size > 0) {
                    if (!row.quote.status || !statusFilter.has(row.quote.status)) {
                        return false;
                    }
                }

                if (scoreFilter === 'top3') {
                    if (!row.scores.rank || row.scores.rank > 3) {
                        return false;
                    }
                } else if (scoreFilter === 'best') {
                    if ((row.scores.composite ?? 0) < 0.8) {
                        return false;
                    }
                }

                return true;
            }),
        [candidateRows, scoreFilter, shortlistOnly, shortlistedQuoteIds, statusFilter, supplierQuery],
    );

    const sortedRows = useMemo(
        () => sortComparisonRows(filteredRows, sortField, sortDirection),
        [filteredRows, sortDirection, sortField],
    );

    const quotesForMatrix = useMemo(() => sortedRows.map((row) => row.quote), [sortedRows]);
    const lineDefinitions = useMemo(
        () => buildLineDefinitions(rfqItems, quotesForMatrix),
        [quotesForMatrix, rfqItems],
    );
    const quoteItemIndex = useMemo(() => buildQuoteItemIndex(sortedRows), [sortedRows]);
    const lineOutliers = useMemo(() => computeLineOutlierStats(sortedRows), [sortedRows]);

    const statusOptions = useMemo(() => {
        const uniques = new Set<Quote['status']>();
        quotes.forEach((quote) => {
            if (quote.status) {
                uniques.add(quote.status);
            }
        });
        return Array.from(uniques);
    }, [quotes]);

    const filtersActive =
        shortlistOnly || statusFilter.size > 0 || supplierQuery.trim().length > 0 || scoreFilter !== 'all';
    const selectedScoreHelper = SCORE_FILTER_OPTIONS.find((option) => option.value === scoreFilter)?.helper;
    const hasFilterMatches = sortedRows.length > 0;
    const hasEnoughQuotes = candidateRows.length >= 2;
    const directionLabel = sortDirection === 'desc' ? 'High → Low' : 'Low → High';
    const showFallbackNotice = comparisonRows.length === 0 && comparisonQuery.isSuccess;

    const handleStatusToggle = (status: Quote['status']) => {
        setStatusFilter((current) => {
            const next = new Set(current);
            if (next.has(status)) {
                next.delete(status);
            } else {
                next.add(status);
            }
            return next;
        });
    };

    const handleResetFilters = () => {
        setSupplierQuery('');
        setScoreFilter('all');
        setShortlistOnly(false);
        setStatusFilter(new Set());
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="bottom"
                className="h-screen w-full overflow-hidden rounded-t-3xl border-t border-sidebar-border/60 bg-background/95 pb-0 sm:max-w-full"
            >
                <SheetHeader className="px-4 pt-4">
                    <SheetTitle>Compare quotes</SheetTitle>
                    <SheetDescription>
                        Review supplier responses, sort by normalized scores, and inspect line-level coverage before awarding.
                    </SheetDescription>
                </SheetHeader>

                <div className="flex flex-col gap-4 overflow-hidden px-4 pb-6">
                    {!hasEnoughQuotes ? (
                        <div className="rounded-xl border border-dashed border-muted-foreground/40 bg-muted/20 px-4 py-6 text-center text-sm text-muted-foreground">
                            Select at least two quotes with the compare toggle to open this view.
                        </div>
                    ) : (
                        <Fragment>
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="flex flex-wrap items-center gap-3">
                                    <div className="w-64">
                                        <Select value={sortField} onValueChange={(value) => setSortField(value as ComparisonSort)}>
                                            <SelectTrigger aria-label="Sort comparison rows">
                                                <SelectValue placeholder="Sort by" />
                                            </SelectTrigger>
                                            <SelectContent align="start">
                                                {SORT_OPTIONS.map((option) => (
                                                    <SelectItem key={option.value} value={option.value}>
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setSortDirection((current) => (current === 'desc' ? 'asc' : 'desc'))}
                                    >
                                        <ArrowUpDown className="mr-2 h-4 w-4" />
                                        {directionLabel}
                                    </Button>
                                </div>
                                <div className="flex flex-wrap items-center gap-3">
                                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                        {comparisonQuery.isFetching ? (
                                            <span className="inline-flex items-center gap-1">
                                                <Loader2 className="h-3.5 w-3.5 animate-spin" /> Syncing scores…
                                            </span>
                                        ) : null}
                                        <span>Scores powered by QuoteComparisonService</span>
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        className="shrink-0"
                                        onClick={handleLaunchAwardFlow}
                                        disabled={!canLaunchAwardFlow}
                                        title={canLaunchAwardFlow ? 'Review winners and convert to POs' : 'Select at least one quote to continue'}
                                    >
                                        <Award className="mr-2 h-4 w-4" /> Review & award
                                    </Button>
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="flex flex-1 flex-wrap items-center gap-3">
                                    <Input
                                        value={supplierQuery}
                                        onChange={(event) => setSupplierQuery(event.currentTarget.value)}
                                        placeholder="Filter suppliers by name"
                                        aria-label="Filter suppliers by name"
                                        className="max-w-xs"
                                    />
                                    <div className="w-48 min-w-[180px]">
                                        <Select value={scoreFilter} onValueChange={(value) => setScoreFilter(value as ScoreFilter)}>
                                            <SelectTrigger aria-label="Filter quotes by score">
                                                <SelectValue placeholder="Score filter" />
                                            </SelectTrigger>
                                            <SelectContent align="start">
                                                {SCORE_FILTER_OPTIONS.map((option) => (
                                                    <SelectItem key={option.value} value={option.value}>
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant={shortlistOnly ? 'default' : 'outline'}
                                        aria-pressed={shortlistOnly}
                                        onClick={() => setShortlistOnly((current) => !current)}
                                    >
                                        Shortlist only
                                    </Button>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    {statusOptions.map((status) => {
                                        if (!status) {
                                            return null;
                                        }
                                        const isActive = statusFilter.has(status);
                                        return (
                                            <Button
                                                key={status}
                                                type="button"
                                                size="sm"
                                                variant={isActive ? 'secondary' : 'ghost'}
                                                aria-pressed={isActive}
                                                onClick={() => handleStatusToggle(status)}
                                            >
                                                {formatQuoteStatusLabel(status)}
                                            </Button>
                                        );
                                    })}
                                    {filtersActive ? (
                                        <Button type="button" variant="link" size="sm" onClick={handleResetFilters}>
                                            Reset filters
                                        </Button>
                                    ) : null}
                                </div>
                            </div>
                            {selectedScoreHelper ? (
                                <p className="text-xs text-muted-foreground">{selectedScoreHelper}</p>
                            ) : null}

                            {comparisonQuery.isError ? (
                                <div className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                                    Unable to load normalized scores. Showing raw quote data.
                                </div>
                            ) : null}

                            {showFallbackNotice ? (
                                <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                    Scores will appear once the comparison service responds. Raw quote totals are shown for now.
                                </div>
                            ) : null}

                            {!hasFilterMatches ? (
                                <div className="rounded-xl border border-dashed border-muted-foreground/40 bg-muted/20 px-4 py-6 text-center text-sm text-muted-foreground">
                                    No quotes match the current filters. {filtersActive ? 'Reset filters to see all suppliers.' : ''}
                                </div>
                            ) : (
                                <Fragment>
                                    <div className="grid gap-3 md:grid-cols-[minmax(280px,1fr)_repeat(auto-fit,minmax(260px,1fr))]">
                                        {sortedRows.map((row) => {
                                            const { quote } = row;
                                            const supplierName = quote.supplier?.name ?? `Supplier #${quote.supplierId}`;
                                            const isShortlisted = Boolean(
                                                quote.isShortlisted ?? shortlistedQuoteIds?.has(quote.id),
                                            );
                                            const isUpdatingShortlist = shortlistMutation.isPending && shortlistTargetId === quote.id;

                                            return (
                                                <div
                                                    key={quote.id}
                                                    className={cn(
                                                        'flex flex-col gap-3 rounded-2xl border border-sidebar-border/60 bg-card/80 p-4 text-sm shadow-sm',
                                                        isShortlisted && 'border-emerald-500/70 bg-emerald-50/80',
                                                    )}
                                                >
                                                    <div className="flex items-start justify-between gap-3">
                                                        <div>
                                                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Supplier</p>
                                                            <p className="text-base font-semibold text-foreground">{supplierName}</p>
                                                            {isShortlisted ? (
                                                                <span className="text-xs font-medium text-emerald-700">Shortlisted</span>
                                                            ) : null}
                                                        </div>
                                                        <div className="flex flex-col items-end gap-2 text-right">
                                                            <Badge variant="secondary" className="bg-muted text-xs font-semibold">
                                                                Rank #{row.scores.rank || '—'}
                                                            </Badge>
                                                            <QuoteStatusBadge status={quote.status} />
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant={isShortlisted ? 'secondary' : 'outline'}
                                                                disabled={isUpdatingShortlist}
                                                                onClick={() => handleShortlistToggle(quote, row.rfqId ?? rfqId)}
                                                            >
                                                                {isUpdatingShortlist ? (
                                                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                                ) : (
                                                                    <Star
                                                                        className={cn(
                                                                            'mr-2 h-4 w-4',
                                                                            isShortlisted && 'fill-current',
                                                                        )}
                                                                    />
                                                                )}
                                                                {isShortlisted ? 'Shortlisted' : 'Shortlist'}
                                                            </Button>
                                                        </div>
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <MoneyCell amountMinor={quote.totalMinor} currency={quote.currency} label="Quote total" />
                                                        <MoneyCell
                                                            amountMinor={quote.taxAmountMinor}
                                                            currency={quote.currency}
                                                            label="Estimated tax"
                                                        />
                                                        <div className="flex items-center gap-2">
                                                            <DeliveryLeadTimeChip leadTimeDays={quote.leadTimeDays} />
                                                            <span className="text-xs text-muted-foreground">Rev {quote.revisionNo ?? 1}</span>
                                                        </div>
                                                        <ScoreBreakdown
                                                            composite={row.scores.composite}
                                                            price={row.scores.price}
                                                            leadTime={row.scores.leadTime}
                                                            risk={row.scores.risk}
                                                            fit={row.scores.fit}
                                                        />
                                                        <div className="text-xs text-muted-foreground">
                                                            Incoterm:{' '}
                                                            <span className="font-medium">{quote.incoterm ?? '—'}</span>
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            Payment terms:{' '}
                                                            <span className="font-medium">{quote.paymentTerms ?? '—'}</span>
                                                        </div>
                                                        <div className="flex flex-wrap gap-3 text-xs text-muted-foreground">
                                                            <span>Attachments: {row.attachmentsCount ?? 0}</span>
                                                            <span>
                                                                Submitted:{' '}
                                                                {quote.submittedAt
                                                                    ? formatDate(quote.submittedAt, {
                                                                          dateStyle: 'medium',
                                                                          timeStyle: 'short',
                                                                      })
                                                                    : '—'}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>

                                    <div className="overflow-auto rounded-2xl border border-sidebar-border/60">
                                        <table className="min-w-[760px] table-fixed w-full text-sm">
                                            <thead className="bg-muted/50 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                                <tr>
                                                    <th className="w-64 px-4 py-3 font-semibold">RFQ line</th>
                                                    {quotesForMatrix.map((quote) => (
                                                        <th key={`header-${quote.id}`} className="px-4 py-3 font-semibold">
                                                            {quote.supplier?.name ?? `Supplier #${quote.supplierId}`}
                                                        </th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {lineDefinitions.length === 0 ? (
                                                    <tr>
                                                        <td colSpan={quotesForMatrix.length + 1} className="px-4 py-6 text-center text-muted-foreground">
                                                            No RFQ lines available for comparison yet.
                                                        </td>
                                                    </tr>
                                                ) : (
                                                    lineDefinitions.map((line) => (
                                                        <tr key={line.id} className="border-t border-sidebar-border/40">
                                                            <td
                                                                scope="row"
                                                                className="bg-muted/30 px-4 py-4 text-left text-sm font-medium text-foreground"
                                                            >
                                                                <div className="flex flex-col gap-1">
                                                                    <span>
                                                                        Line {line.lineNo ?? '—'} · {line.label}
                                                                    </span>
                                                                    {Number.isFinite(line.quantity) ? (
                                                                        <span className="text-xs text-muted-foreground">
                                                                            Qty {formatNumber(line.quantity ?? 0)} {line.uom ?? ''}
                                                                        </span>
                                                                    ) : null}
                                                                    {line.spec ? (
                                                                        <span className="text-xs text-muted-foreground">{line.spec}</span>
                                                                    ) : null}
                                                                </div>
                                                            </td>
                                                            {quotesForMatrix.map((quote) => {
                                                                const lineMap = quoteItemIndex.get(quote.id);
                                                                const item = lineMap?.get(line.id);
                                                                const outlierStats = lineOutliers.get(line.id);

                                                                if (!item) {
                                                                    return (
                                                                        <td key={`${quote.id}-${line.id}`} className="px-4 py-4 align-top text-xs text-muted-foreground">
                                                                            No quote
                                                                        </td>
                                                                    );
                                                                }

                                                                const extendedMinor =
                                                                    item.lineTotalMinor ??
                                                                    item.lineSubtotalMinor ??
                                                                    (item.unitPriceMinor ?? 0) * (item.quantity ?? 1);

                                                                return (
                                                                    <td key={`${quote.id}-${line.id}`} className="px-4 py-4 align-top">
                                                                        <div className="space-y-3">
                                                                            <MoneyCell
                                                                                amountMinor={item.unitPriceMinor}
                                                                                currency={item.currency ?? quote.currency}
                                                                                label="Unit price"
                                                                            />
                                                                            <OutlierBadge
                                                                                kind="price"
                                                                                stats={outlierStats}
                                                                                value={item.unitPriceMinor ?? undefined}
                                                                            />
                                                                            <MoneyCell
                                                                                amountMinor={extendedMinor}
                                                                                currency={item.currency ?? quote.currency}
                                                                                label="Extended"
                                                                            />
                                                                            <DeliveryLeadTimeChip
                                                                                leadTimeDays={item.leadTimeDays ?? quote.leadTimeDays}
                                                                            />
                                                                            <OutlierBadge
                                                                                kind="lead"
                                                                                stats={outlierStats}
                                                                                value={item.leadTimeDays ?? quote.leadTimeDays ?? undefined}
                                                                            />
                                                                            {item.note ? (
                                                                                <p className="text-xs text-muted-foreground">{item.note}</p>
                                                                            ) : null}
                                                                        </div>
                                                                    </td>
                                                                );
                                                            })}
                                                        </tr>
                                                    ))
                                                )}
                                            </tbody>
                                            <tfoot className="bg-muted/30 text-sm">
                                                <tr>
                                                    <th className="px-4 py-3 text-left font-semibold">Totals</th>
                                                    {quotesForMatrix.map((quote) => (
                                                        <td key={`total-${quote.id}`} className="px-4 py-3 align-top">
                                                            <MoneyCell amountMinor={quote.totalMinor} currency={quote.currency} label="Grand total" />
                                                        </td>
                                                    ))}
                                                </tr>
                                                <tr>
                                                    <th className="px-4 py-3 text-left font-semibold">Lead time</th>
                                                    {quotesForMatrix.map((quote) => (
                                                        <td key={`lead-${quote.id}`} className="px-4 py-3 align-top">
                                                            <DeliveryLeadTimeChip leadTimeDays={quote.leadTimeDays} />
                                                        </td>
                                                    ))}
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </Fragment>
                            )}
                        </Fragment>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}

function buildFallbackRow(quote: Quote): QuoteComparisonRow {
    return {
        quoteId: quote.id,
        rfqId: quote.rfqId,
        supplier: quote.supplier,
        currency: quote.currency,
        totalPriceMinor: quote.totalMinor,
        leadTimeDays: quote.leadTimeDays,
        status: quote.status,
        attachmentsCount: quote.attachments?.length ?? 0,
        submittedAt: quote.submittedAt,
        scores: {
            composite: 0,
            price: 0,
            leadTime: 0,
            risk: 0,
            fit: 0,
            rank: 0,
        },
        quote,
    };
}

function sortComparisonRows(rows: QuoteComparisonRow[], field: ComparisonSort, direction: 'asc' | 'desc'): QuoteComparisonRow[] {
    const multiplier = direction === 'asc' ? 1 : -1;

    return [...rows].sort((a, b) => {
        const valueA = getSortMetric(a, field);
        const valueB = getSortMetric(b, field);
        if (valueA === valueB) {
            return 0;
        }
        return valueA > valueB ? multiplier : -multiplier;
    });
}

function getSortMetric(row: QuoteComparisonRow, field: ComparisonSort): number {
    switch (field) {
        case 'price':
            return row.scores.price;
        case 'leadTime':
            return row.scores.leadTime;
        case 'risk':
            return row.scores.risk;
        case 'fit':
            return row.scores.fit;
        default:
            return row.scores.composite;
    }
}

function buildLineDefinitions(rfqItems: RfqItem[] | undefined, quotes: Quote[]): LineDefinition[] {
    const map = new Map<string, LineDefinition>();

    rfqItems?.forEach((item) => {
        const key = String(item.id);
        map.set(key, {
            id: key,
            lineNo: item.lineNo,
            label: item.partName,
            spec: item.spec,
            quantity: item.quantity,
            uom: item.uom,
        });
    });

    quotes.forEach((quote) => {
        quote.items?.forEach((item) => {
            const key = item.rfqItemId;
            if (!map.has(key)) {
                map.set(key, {
                    id: key,
                    label: `Line ${key}`,
                });
            }
        });
    });

    return Array.from(map.values()).sort((a, b) => {
        const lineA = a.lineNo ?? FALLBACK_ORDER;
        const lineB = b.lineNo ?? FALLBACK_ORDER;
        if (lineA !== lineB) {
            return lineA - lineB;
        }

        return a.label.localeCompare(b.label);
    });
}

function buildQuoteItemIndex(rows: QuoteComparisonRow[]): Map<string, Map<string, QuoteItem>> {
    const index = new Map<string, Map<string, QuoteItem>>();

    rows.forEach((row) => {
        const lineMap = new Map<string, QuoteItem>();
        row.quote.items?.forEach((item) => {
            lineMap.set(String(item.rfqItemId), item);
        });
        index.set(row.quote.id, lineMap);
    });

    return index;
}

interface LineOutlierStats {
    priceLow?: number;
    priceHigh?: number;
    leadLow?: number;
    leadHigh?: number;
}

function computeLineOutlierStats(rows: QuoteComparisonRow[]): Map<string, LineOutlierStats> {
    const values = new Map<string, { prices: number[]; leads: number[] }>();

    rows.forEach((row) => {
        row.quote.items?.forEach((item) => {
            const key = String(item.rfqItemId);
            const bucket = values.get(key) ?? { prices: [], leads: [] };
            if (typeof item.unitPriceMinor === 'number' && item.unitPriceMinor > 0) {
                bucket.prices.push(item.unitPriceMinor);
            }

            const lead = item.leadTimeDays ?? row.quote.leadTimeDays;
            if (typeof lead === 'number' && lead > 0) {
                bucket.leads.push(lead);
            }

            values.set(key, bucket);
        });
    });

    const stats = new Map<string, LineOutlierStats>();

    values.forEach((bucket, lineId) => {
        const entry: LineOutlierStats = {};
        if (bucket.prices.length >= 2) {
            const { low, high } = deriveThresholds(bucket.prices);
            entry.priceLow = low;
            entry.priceHigh = high;
        }

        if (bucket.leads.length >= 2) {
            const { low, high } = deriveThresholds(bucket.leads);
            entry.leadLow = low;
            entry.leadHigh = high;
        }

        if (Object.keys(entry).length) {
            stats.set(lineId, entry);
        }
    });

    return stats;
}

function deriveThresholds(values: number[], tolerance = 0.2): { low?: number; high?: number } {
    if (!values.length) {
        return {};
    }

    const sorted = [...values].sort((a, b) => a - b);
    const median = computeMedian(sorted);
    if (median <= 0) {
        return {};
    }

    const low = median * (1 - tolerance);
    const high = median * (1 + tolerance);
    return { low, high };
}

function computeMedian(values: number[]): number {
    if (!values.length) {
        return 0;
    }

    const mid = Math.floor(values.length / 2);
    if (values.length % 2 === 0) {
        return (values[mid - 1] + values[mid]) / 2;
    }
    return values[mid];
}

function OutlierBadge({
    kind,
    value,
    stats,
}: {
    kind: 'price' | 'lead';
    value?: number;
    stats?: LineOutlierStats;
}) {
    if (value == null || !stats) {
        return null;
    }

    if (kind === 'price') {
        if (stats.priceLow != null && value < stats.priceLow) {
            return (
                <Badge variant="secondary" className="bg-emerald-100 text-emerald-800">
                    Low price outlier
                </Badge>
            );
        }

        if (stats.priceHigh != null && value > stats.priceHigh) {
            return (
                <Badge variant="destructive" className="text-white">
                    High price outlier
                </Badge>
            );
        }
    }

    if (kind === 'lead') {
        if (stats.leadLow != null && value < stats.leadLow) {
            return (
                <Badge variant="secondary" className="bg-sky-100 text-sky-900">
                    Fast lead outlier
                </Badge>
            );
        }

        if (stats.leadHigh != null && value > stats.leadHigh) {
            return (
                <Badge variant="destructive" className="text-white">
                    Slow lead outlier
                </Badge>
            );
        }
    }

    return null;
}

function ScoreBreakdown({
    composite,
    price,
    leadTime,
    risk,
    fit,
}: {
    composite: number;
    price: number;
    leadTime: number;
    risk: number;
    fit: number;
}) {
    return (
        <div className="grid gap-1 rounded-lg border border-muted-foreground/20 bg-muted/20 p-2 text-xs">
            <ScoreRow label="Composite score" value={composite} accent="text-foreground" />
            <ScoreRow label="Fit" value={fit} />
            <ScoreRow label="Lead time" value={leadTime} />
            <ScoreRow label="Price" value={price} />
            <ScoreRow label="Risk" value={risk} />
        </div>
    );
}

function ScoreRow({ label, value, accent }: { label: string; value: number; accent?: string }) {
    return (
        <div className="flex items-center justify-between gap-2">
            <span className="text-muted-foreground">{label}</span>
            <span className={cn('font-semibold', accent)}>{formatScorePercent(value)}</span>
        </div>
    );
}

function formatScorePercent(score: number): string {
    const normalized = Number.isFinite(score) ? Math.max(0, Math.min(score, 1)) : 0;
    return `${(normalized * 100).toFixed(0)}%`;
}

function formatQuoteStatusLabel(status: Quote['status']): string {
    return status
        ? status
              .replace(/_/g, ' ')
              .split(' ')
              .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
              .join(' ')
        : 'Status';
}
