import { useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { ArrowRight, Filter, PackageSearch, RotateCcw, ShieldAlert, X } from 'lucide-react';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { DiscrepancyBadge } from '@/components/matching/discrepancy-badge';
import { MatchSummaryCard } from '@/components/matching/match-summary-card';
import { PlanUpgradeBanner } from '@/components/plan-upgrade-banner';
import { EmptyState } from '@/components/empty-state';
import { SupplierDirectoryPicker } from '@/components/rfqs/supplier-directory-picker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { use3WayMatch } from '@/hooks/api/matching/use-3-way-match';
import { useMatchCandidates } from '@/hooks/api/matching/use-match-candidates';
import type { MatchCandidate, MatchResolutionInput, Supplier } from '@/types/sourcing';
import { cn } from '@/lib/utils';

const STATUS_OPTIONS = [
    { value: 'all', label: 'All statuses' },
    { value: 'variance', label: 'Variance' },
    { value: 'pending', label: 'Pending review' },
    { value: 'resolved', label: 'Resolved' },
];

const PER_PAGE = 25;

type CursorMeta = {
    next_cursor?: string | null;
    prev_cursor?: string | null;
    [key: string]: unknown;
};

export const MatchWorkbenchPage = () => {
    const { hasFeature, state } = useAuth();
    const { formatMoney, formatNumber } = useFormatting();
    const featureFlagsLoaded = state.status !== 'idle' && state.status !== 'loading';
    const matchingEnabled = hasFeature('finance_enabled');

    const [statusFilter, setStatusFilter] = useState('variance');
    const [search, setSearch] = useState('');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const [supplierPickerOpen, setSupplierPickerOpen] = useState(false);
    const [selectedSupplier, setSelectedSupplier] = useState<Supplier | null>(null);
    const [supplierFilter, setSupplierFilter] = useState('');
    const [cursor, setCursor] = useState<string | null>(null);
    const [selectedId, setSelectedId] = useState<string | null>(null);

    const supplierId = useMemo(() => {
        const parsed = Number(supplierFilter);
        return Number.isFinite(parsed) ? parsed : undefined;
    }, [supplierFilter]);

    const matchCandidatesQuery = useMatchCandidates({
        cursor,
        perPage: PER_PAGE,
        search: search || undefined,
        status: statusFilter === 'all' ? undefined : statusFilter,
        supplierId,
        dateFrom: dateFrom || undefined,
        dateTo: dateTo || undefined,
    });

    const candidates = useMemo(() => matchCandidatesQuery.data?.items ?? [], [matchCandidatesQuery.data?.items]);
    const cursorMeta = (matchCandidatesQuery.data?.meta ?? null) as CursorMeta | null;
    const nextCursor = typeof cursorMeta?.next_cursor === 'string' ? cursorMeta.next_cursor : null;
    const prevCursor = typeof cursorMeta?.prev_cursor === 'string' ? cursorMeta.prev_cursor : null;

    const activeSelectedId = useMemo(() => {
        if (selectedId && candidates.some((candidate) => candidate.id === selectedId)) {
            return selectedId;
        }
        return candidates[0]?.id ?? null;
    }, [candidates, selectedId]);

    const selectedCandidate: MatchCandidate | undefined = candidates.find((candidate) => candidate.id === activeSelectedId);

    const { mutate: resolveMatch, isPending: resolvingMatch } = use3WayMatch();

    const handleSubmit = (payload: MatchResolutionInput) => {
        resolveMatch(payload);
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

    const handleResetFilters = () => {
        setStatusFilter('variance');
        setSearch('');
        setDateFrom('');
        setDateTo('');
        setSupplierFilter('');
        setSelectedSupplier(null);
        setCursor(null);
    };

    if (featureFlagsLoaded && !matchingEnabled) {
        return (
            <div className="flex flex-1 flex-col gap-6">
                <Helmet>
                    <title>Matching workbench</title>
                </Helmet>
                <PlanUpgradeBanner />
                <EmptyState
                    title="3-way matching locked"
                    description="Upgrade your Elements Supply plan to unlock invoice matching, credits, and variance workflows."
                    icon={<ShieldAlert className="h-12 w-12 text-muted-foreground" />}
                />
            </div>
        );
    }

    const formatCandidateMoney = (amountMinor?: number | null, currency?: string | null) =>
        formatMoney(typeof amountMinor === 'number' ? amountMinor / 100 : null, {
            currency: currency ?? undefined,
        });

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Matching workbench</title>
            </Helmet>
            <WorkspaceBreadcrumbs />
            <PlanUpgradeBanner />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Finance</p>
                    <h1 className="text-2xl font-semibold text-foreground">3-way match workbench</h1>
                    <p className="text-sm text-muted-foreground">
                        Reconcile invoices, POs, and receipts with a guided review queue.
                    </p>
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
                                setCursor(null);
                            }}
                        >
                            <SelectTrigger className="h-9">
                                <SelectValue placeholder="Variance" />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUS_OPTIONS.map((option) => (
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
                                <ArrowRight className="h-4 w-4 opacity-60" />
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
                    </div>
                    <div className="space-y-2">
                        <label className="text-xs font-medium uppercase text-muted-foreground">Search</label>
                        <Input
                            placeholder="PO, invoice, notes"
                            value={search}
                            onChange={(event) => {
                                setSearch(event.target.value);
                                setCursor(null);
                            }}
                        />
                    </div>
                    <div className="space-y-2">
                        <label className="text-xs font-medium uppercase text-muted-foreground">Activity from</label>
                        <Input
                            type="date"
                            value={dateFrom}
                            onChange={(event) => {
                                setDateFrom(event.target.value);
                                setCursor(null);
                            }}
                        />
                    </div>
                    <div className="space-y-2">
                        <label className="text-xs font-medium uppercase text-muted-foreground">Activity to</label>
                        <Input
                            type="date"
                            value={dateTo}
                            onChange={(event) => {
                                setDateTo(event.target.value);
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

            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_420px]">
                <Card className="border-border/70">
                    <CardContent className="space-y-4 py-4">
                        <div className="flex items-center justify-between">
                            <p className="text-sm font-medium text-muted-foreground">Variance queue</p>
                            <Badge variant="outline" className="text-xs font-mono">
                                {matchCandidatesQuery.isLoading ? '…' : `${formatNumber(candidates.length, { maximumFractionDigits: 0 })} items`}
                            </Badge>
                        </div>
                        <Separator />
                        <div className="space-y-3">
                            {matchCandidatesQuery.isLoading && (
                                <div className="space-y-3">
                                    {Array.from({ length: 3 }).map((_, index) => (
                                        <Skeleton key={`skeleton-${index}`} className="h-24 w-full" />
                                    ))}
                                </div>
                            )}

                            {!matchCandidatesQuery.isLoading && !candidates.length ? (
                                <EmptyState
                                    title="Clean books"
                                    description="No active variances require review."
                                    icon={<PackageSearch className="h-10 w-10 text-muted-foreground" />}
                                />
                            ) : null}

                            {candidates.map((candidate) => (
                                <button
                                    key={candidate.id}
                                    type="button"
                                    className={cn(
                                        'w-full rounded-lg border border-border/70 p-4 text-left transition hover:border-primary/60',
                                        activeSelectedId === candidate.id ? 'border-primary bg-primary/5' : 'bg-background',
                                    )}
                                    onClick={() => setSelectedId(candidate.id)}
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p className="text-sm font-semibold text-foreground">
                                                {candidate.purchaseOrderNumber ?? `PO-${candidate.purchaseOrderId}`}
                                            </p>
                                            <p className="text-xs text-muted-foreground">{candidate.supplierName ?? 'Supplier'}</p>
                                        </div>
                                        <Badge variant="secondary" className={cn('capitalize text-xs', statusToClass(candidate.status))}>
                                            {candidate.status}
                                        </Badge>
                                    </div>
                                    <div className="mt-3 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                        <span>PO {formatCandidateMoney(candidate.poTotalMinor, candidate.currency)}</span>
                                        <span>Received {formatCandidateMoney(candidate.receivedTotalMinor, candidate.currency)}</span>
                                        <span>Invoiced {formatCandidateMoney(candidate.invoicedTotalMinor, candidate.currency)}</span>
                                    </div>
                                    <div className="mt-3 flex flex-wrap items-center gap-2">
                                        {candidate.lines.slice(0, 2).map((line) =>
                                            line.discrepancies.map((disc) => (
                                                <DiscrepancyBadge key={`${line.id}-${disc.id}`} discrepancy={disc} />
                                            )),
                                        )}
                                        {candidate.lines.length > 2 && (
                                            <Badge variant="outline" className="text-xs">
                                                +{formatNumber(candidate.lines.length - 2, { maximumFractionDigits: 0 })} more lines
                                            </Badge>
                                        )}
                                    </div>
                                </button>
                            ))}
                        </div>

                        <div className="flex items-center justify-between rounded-lg border border-border/60 bg-background/60 px-3 py-2 text-sm">
                            <span className="text-muted-foreground">Cursor pagination</span>
                            <div className="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setCursor(prevCursor ?? null)}
                                    disabled={matchCandidatesQuery.isLoading || (!prevCursor && cursor === null)}
                                >
                                    Previous
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => nextCursor && setCursor(nextCursor)}
                                    disabled={matchCandidatesQuery.isLoading || !nextCursor}
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <MatchSummaryCard candidate={selectedCandidate} onSubmit={handleSubmit} isSubmitting={resolvingMatch} />
            </div>

            <SupplierDirectoryPicker
                open={supplierPickerOpen}
                onOpenChange={setSupplierPickerOpen}
                onSelect={handleSupplierSelected}
            />
        </div>
    );
};

const statusToClass = (status: MatchCandidate['status']) => {
    switch (status) {
        case 'variance':
            return 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-200';
        case 'pending':
            return 'bg-sky-50 text-sky-800 dark:bg-sky-500/10 dark:text-sky-200';
        case 'resolved':
            return 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200';
        default:
            return 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100';
    }
};
