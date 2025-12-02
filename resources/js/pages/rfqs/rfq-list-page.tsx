import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useRfqs, type RfqStatusFilter } from '@/hooks/api/use-rfqs';
import { cn } from '@/lib/utils';
import { Files, PlusCircle } from 'lucide-react';
import { Helmet } from 'react-helmet-async';
import { NavLink, Link } from 'react-router-dom';
import { useEffect, useState, type ChangeEvent } from 'react';
import { useFormatting } from '@/contexts/formatting-context';
import { getRfqMethodLabel } from '@/constants/rfq';
import type { Rfq } from '@/sdk';

function getStatusPresentation(status?: string) {
    switch (status) {
        case 'draft':
            return { label: 'Draft', variant: 'secondary' as const };
        case 'open':
            return { label: 'Open', variant: 'default' as const };
        case 'closed':
            return { label: 'Closed', variant: 'outline' as const };
        case 'awarded':
            return { label: 'Awarded', variant: 'default' as const, className: 'bg-emerald-500 text-white' };
        case 'cancelled':
            return { label: 'Cancelled', variant: 'outline' as const, className: 'text-muted-foreground' };
        default:
            return { label: status ?? 'Unknown', variant: 'outline' as const };
    }
}

function resolveQuantityTotal(rfq: Rfq): number | null {
    const extended = rfq as Rfq & {
        quantityTotal?: number | null;
        items?: Array<{ quantity?: number | string | null }>;
    };

    if (typeof extended.quantityTotal === 'number') {
        return extended.quantityTotal;
    }

    if (typeof (rfq as { quantity?: number | null }).quantity === 'number') {
        return (rfq as { quantity?: number | null }).quantity ?? null;
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

const STATUS_OPTIONS: Array<{ value: RfqStatusFilter; label: string }> = [
    { value: 'all', label: 'All statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'open', label: 'Open' },
    { value: 'awarded', label: 'Awarded' },
    { value: 'closed', label: 'Closed' },
    { value: 'cancelled', label: 'Cancelled' },
];

type BiddingFilter = 'all' | 'open' | 'private';

const BIDDING_OPTIONS: Array<{ value: BiddingFilter; label: string }> = [
    { value: 'all', label: 'All bidding modes' },
    { value: 'open', label: 'Open bidding only' },
    { value: 'private', label: 'Private invitations only' },
];

export function RfqListPage() {
    const { formatNumber, formatDate } = useFormatting();
    const [statusFilter, setStatusFilter] = useState<RfqStatusFilter>('all');
    const [searchInput, setSearchInput] = useState('');
    const [searchTerm, setSearchTerm] = useState('');
    const [biddingFilter, setBiddingFilter] = useState<BiddingFilter>('all');
    const [dateFrom, setDateFrom] = useState<string | undefined>();
    const [dateTo, setDateTo] = useState<string | undefined>();
    const [cursor, setCursor] = useState<string | undefined>();
    const perPage = 10;

    useEffect(() => {
        const handle = window.setTimeout(() => setSearchTerm(searchInput.trim()), 300);
        return () => window.clearTimeout(handle);
    }, [searchInput]);

    const handleStatusChange = (value: RfqStatusFilter) => {
        setStatusFilter(value);
        setCursor(undefined);
    };

    const handleSearchChange = (event: ChangeEvent<HTMLInputElement>) => {
        setSearchInput(event.target.value);
        setCursor(undefined);
    };

    const handleDateFromChange = (event: ChangeEvent<HTMLInputElement>) => {
        setDateFrom(event.target.value || undefined);
        setCursor(undefined);
    };

    const handleDateToChange = (event: ChangeEvent<HTMLInputElement>) => {
        setDateTo(event.target.value || undefined);
        setCursor(undefined);
    };

    const rfqsQuery = useRfqs({
        perPage,
        status: statusFilter,
        search: searchTerm,
        dueFrom: dateFrom,
        dueTo: dateTo,
        cursor,
        openBidding: biddingFilter === 'all' ? undefined : biddingFilter === 'open',
    });

    const { items, cursor: cursorMeta } = rfqsQuery;

    const canGoPrev = Boolean(cursorMeta?.prevCursor || cursor);
    const canGoNext = Boolean(cursorMeta?.nextCursor);

    const showSkeleton = rfqsQuery.isLoading && !rfqsQuery.isError;
    const showEmptyState = !showSkeleton && !rfqsQuery.isError && items.length === 0;

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>RFQs</title>
            </Helmet>

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-foreground">Requests for Quotation</h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Manage sourcing events, monitor supplier engagement, and keep your pipeline of RFQs on track.
                    </p>
                </div>
                <Button asChild size="sm">
                    <Link to="/app/rfqs/new">
                        {/* TODO: confirm RFQ wizard route once implemented */}
                        <PlusCircle className="mr-2 h-4 w-4" />
                        New RFQ
                    </Link>
                </Button>
            </div>

            <div className="flex flex-wrap items-center gap-3 rounded-xl border bg-card px-4 py-3 shadow-sm">
                <div className="flex flex-1 min-w-[220px] items-center gap-2">
                    <Input
                        value={searchInput}
                        onChange={handleSearchChange}
                        placeholder="Search RFQs"
                        className="h-9"
                    />
                </div>
                <div className="flex items-center gap-2">
                    <Select value={statusFilter} onValueChange={(value) => handleStatusChange(value as RfqStatusFilter)}>
                        <SelectTrigger className="w-40 h-9">
                            <SelectValue placeholder="Status" />
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
                <div className="flex items-center gap-2">
                    <Select value={biddingFilter} onValueChange={(value) => setBiddingFilter(value as BiddingFilter)}>
                        <SelectTrigger className="w-48 h-9">
                            <SelectValue placeholder="Bidding" />
                        </SelectTrigger>
                        <SelectContent>
                            {BIDDING_OPTIONS.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="flex items-center gap-2">
                    <label className="text-xs font-medium text-muted-foreground">Due from</label>
                    <Input
                        type="date"
                        value={dateFrom ?? ''}
                        onChange={handleDateFromChange}
                        className="h-9"
                    />
                </div>
                <div className="flex items-center gap-2">
                    <label className="text-xs font-medium text-muted-foreground">Due to</label>
                    <Input
                        type="date"
                        value={dateTo ?? ''}
                        onChange={handleDateToChange}
                        className="h-9"
                    />
                </div>
            </div>

            {rfqsQuery.isError ? (
                <Alert variant="destructive">
                    <AlertTitle>Unable to load RFQs</AlertTitle>
                    <AlertDescription>
                        We ran into an issue retrieving RFQs. Please retry in a moment or refresh the page.
                    </AlertDescription>
                </Alert>
            ) : null}

            <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table className="min-w-full divide-y divide-border">
                    <thead className="bg-muted/40">
                        <tr className="text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <th className="px-4 py-3 font-medium">RFQ</th>
                            <th className="px-4 py-3 font-medium">Title</th>
                            <th className="px-4 py-3 font-medium">Quantity</th>
                            <th className="px-4 py-3 font-medium">Method / Material</th>
                            <th className="px-4 py-3 font-medium">Published / Due</th>
                            <th className="px-4 py-3 font-medium text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border text-sm">
                        {showSkeleton
                            ? Array.from({ length: 6 }).map((_, index) => (
                                  <tr key={`skeleton-${index}`} className="hover:bg-muted/20">
                                      <td className="px-4 py-4">
                                          <Skeleton className="h-4 w-24" />
                                      </td>
                                      <td className="px-4 py-4">
                                          <Skeleton className="h-4 w-32" />
                                      </td>
                                      <td className="px-4 py-4">
                                          <Skeleton className="h-4 w-16" />
                                      </td>
                                      <td className="px-4 py-4">
                                          <Skeleton className="h-4 w-28" />
                                      </td>
                                      <td className="px-4 py-4">
                                          <Skeleton className="h-4 w-24" />
                                      </td>
                                      <td className="px-4 py-4 text-right">
                                          <Skeleton className="ml-auto h-5 w-16" />
                                      </td>
                                  </tr>
                              ))
                                : items.map((rfq) => {
                                    const statusPresentation = getStatusPresentation(rfq.status);
                                    const extended = rfq as Rfq & { title?: string | null };
                                    const derivedTitle = typeof extended.title === 'string' && extended.title.trim().length > 0
                                        ? extended.title
                                        : rfq.itemName;
                                    const quantityTotal = resolveQuantityTotal(rfq);
                                    const methodLabel = getRfqMethodLabel((rfq as { method?: string | null }).method ?? undefined);
                                    const materialLabel = (rfq as { material?: string | null }).material ?? '—';
                                    const publishAt = (rfq as { publishAt?: string | null }).publishAt ?? (rfq as { sentAt?: string | null }).sentAt;
                                    const dueAt = (rfq as { dueAt?: string | null }).dueAt ?? (rfq as { deadlineAt?: string | null }).deadlineAt;

                                  return (
                                      <tr key={rfq.id} className="hover:bg-muted/20">
                                          <td className="px-4 py-4 align-top">
                                              <NavLink to={`/app/rfqs/${rfq.id}`} className="font-medium text-primary">
                                                  {rfq.number}
                                              </NavLink>
                                          </td>
                                          <td className="px-4 py-4 align-top">
                                              <div className="flex flex-col gap-1">
                                                  <span className="font-medium text-foreground">{derivedTitle}</span>
                                                  {(rfq as { deliveryLocation?: string | null }).deliveryLocation ? (
                                                      <span className="text-xs text-muted-foreground">
                                                          {(rfq as { deliveryLocation?: string | null }).deliveryLocation}
                                                      </span>
                                                  ) : null}
                                              </div>
                                          </td>
                                          <td className="px-4 py-4 align-top text-muted-foreground">
                                              <span className="font-medium text-foreground">
                                                  {quantityTotal !== null
                                                      ? formatNumber(quantityTotal, { maximumFractionDigits: 0 })
                                                      : '—'}
                                              </span>
                                          </td>
                                          <td className="px-4 py-4 align-top">
                                              <div className="flex flex-col text-xs text-muted-foreground">
                                                  <span className="font-medium text-foreground">{methodLabel}</span>
                                                  <span>{materialLabel}</span>
                                              </div>
                                          </td>
                                          <td className="px-4 py-4 align-top text-xs text-muted-foreground">
                                              <div className="flex flex-col">
                                                  <span className="font-medium text-foreground">
                                                      {formatDate(publishAt)}
                                                  </span>
                                                  <span>Due {formatDate(dueAt)}</span>
                                              </div>
                                          </td>
                                          <td className="px-4 py-4 align-top text-right">
                                              <Badge
                                                  variant={statusPresentation.variant}
                                                  className={cn('ml-auto', statusPresentation.className)}
                                              >
                                                  {statusPresentation.label}
                                              </Badge>
                                          </td>
                                      </tr>
                                  );
                              })}
                    </tbody>
                </table>

                {showEmptyState ? (
                    <div className="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center">
                        <Files className="h-10 w-10 text-muted-foreground" />
                        <div className="space-y-1">
                            <p className="text-base font-semibold text-foreground">No RFQs yet</p>
                            <p className="max-w-sm text-sm text-muted-foreground">
                                Kick off your first sourcing event by creating an RFQ. Invite suppliers, collect quotes,
                                and track responses in one place.
                            </p>
                        </div>
                        <Button asChild variant="outline" size="sm">
                            <Link to="/app/rfqs/new">Create RFQ</Link>
                        </Button>
                    </div>
                ) : null}
            </div>

            <div className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="text-xs text-muted-foreground">Showing {items.length} RFQs</div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!canGoPrev}
                        onClick={() => setCursor(cursorMeta?.prevCursor ?? undefined)}
                    >
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!canGoNext}
                        onClick={() =>
                            setCursor((current) =>
                                cursorMeta?.nextCursor ? cursorMeta.nextCursor : current,
                            )
                        }
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    );
}
