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
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { Files, PlusCircle } from 'lucide-react';
import { Helmet } from 'react-helmet-async';
import { NavLink, Link } from 'react-router-dom';
import { useEffect, useMemo, useState, type ChangeEvent } from 'react';

function formatDateSafe(value?: Date): string {
    if (!value) {
        return '—';
    }

    return formatDate(value.toISOString());
}

function getStatusPresentation(status?: string) {
    switch (status) {
        case 'awaiting':
            return { label: 'Draft', variant: 'secondary' as const };
        case 'open':
            return { label: 'Open', variant: 'default' as const };
        case 'closed':
            return { label: 'Closed', variant: 'outline' as const };
        case 'awarded':
            return { label: 'Awarded', variant: 'default' as const, className: 'bg-emerald-500 text-white' };
        case 'cancelled':
            return { label: 'Cancelled', variant: 'destructive' as const };
        default:
            return { label: status ?? 'Unknown', variant: 'outline' as const };
    }
}

const STATUS_OPTIONS: { value: RfqStatusFilter; label: string }[] = [
    { value: 'all', label: 'All statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'open', label: 'Open' },
    { value: 'closed', label: 'Closed' },
    { value: 'awarded', label: 'Awarded' },
];

export function RfqListPage() {
    const [page, setPage] = useState(1);
    const [statusFilter, setStatusFilter] = useState<RfqStatusFilter>('all');
    const [searchInput, setSearchInput] = useState('');
    const [searchTerm, setSearchTerm] = useState('');
    const [dateFrom, setDateFrom] = useState<string | undefined>();
    const [dateTo, setDateTo] = useState<string | undefined>();
    const perPage = 10;

    useEffect(() => {
        const handle = window.setTimeout(() => setSearchTerm(searchInput.trim()), 300);
        return () => window.clearTimeout(handle);
    }, [searchInput]);

    const handleStatusChange = (value: RfqStatusFilter) => {
        setStatusFilter(value);
        setPage(1);
    };

    const handleSearchChange = (event: ChangeEvent<HTMLInputElement>) => {
        setSearchInput(event.target.value);
        setPage(1);
    };

    const handleDateFromChange = (event: ChangeEvent<HTMLInputElement>) => {
        setDateFrom(event.target.value || undefined);
        setPage(1);
    };

    const handleDateToChange = (event: ChangeEvent<HTMLInputElement>) => {
        setDateTo(event.target.value || undefined);
        setPage(1);
    };

    const rfqsQuery = useRfqs({
        page,
        perPage,
        status: statusFilter,
        search: searchTerm,
        dateFrom,
        dateTo,
    });

    const { items, meta } = rfqsQuery;

    const paginationInfo = useMemo(() => {
        if (!meta) {
            return { from: 0, to: 0, total: 0 };
        }

        if (items.length === 0) {
            return { from: 0, to: 0, total: meta.total ?? 0 };
        }

        const start = (meta.currentPage - 1) * meta.perPage + 1;
        const end = Math.min(meta.currentPage * meta.perPage, meta.total);

        return {
            from: start,
            to: end,
            total: meta.total,
        };
    }, [items.length, meta]);

    const canGoPrev = meta ? meta.currentPage > 1 : false;
    const canGoNext = meta ? meta.currentPage < meta.lastPage : false;

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
                    <label className="text-xs font-medium text-muted-foreground">Published from</label>
                    <Input
                        type="date"
                        value={dateFrom ?? ''}
                        onChange={handleDateFromChange}
                        className="h-9"
                    />
                </div>
                <div className="flex items-center gap-2">
                    <label className="text-xs font-medium text-muted-foreground">Published to</label>
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

                                  return (
                                      <tr key={rfq.id} className="hover:bg-muted/20">
                                          <td className="px-4 py-4 align-top">
                                              <NavLink to={`/app/rfqs/${rfq.id}`} className="font-medium text-primary">
                                                  {rfq.number}
                                              </NavLink>
                                          </td>
                                          <td className="px-4 py-4 align-top">
                                              <div className="flex flex-col gap-1">
                                                  <span className="font-medium text-foreground">{rfq.itemName}</span>
                                                  {rfq.clientCompany ? (
                                                      <span className="text-xs text-muted-foreground">
                                                          {rfq.clientCompany}
                                                      </span>
                                                  ) : null}
                                              </div>
                                          </td>
                                          <td className="px-4 py-4 align-top text-muted-foreground">
                                              <span className="font-medium text-foreground">{rfq.quantity.toLocaleString()}</span>
                                          </td>
                                          <td className="px-4 py-4 align-top">
                                              <div className="flex flex-col text-xs text-muted-foreground">
                                                  <span className="font-medium text-foreground">{rfq.method}</span>
                                                  <span>{rfq.material}</span>
                                              </div>
                                          </td>
                                          <td className="px-4 py-4 align-top text-xs text-muted-foreground">
                                              <div className="flex flex-col">
                                                  <span className="font-medium text-foreground">
                                                      {formatDateSafe(rfq.sentAt)}
                                                  </span>
                                                  <span>Due {formatDateSafe(rfq.deadlineAt)}</span>
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
                <div className="text-xs text-muted-foreground">
                    Showing {paginationInfo.from ? `${paginationInfo.from}–${paginationInfo.to}` : '0'} of{' '}
                    {paginationInfo.total} RFQs
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!canGoPrev}
                        onClick={() => setPage((current) => Math.max(1, current - 1))}
                    >
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={!canGoNext}
                        onClick={() => setPage((current) => (meta ? Math.min(meta.lastPage, current + 1) : current))}
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    );
}
