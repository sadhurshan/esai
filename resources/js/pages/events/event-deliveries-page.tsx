import { useCallback, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { formatDistanceToNow } from 'date-fns';
import { AlertTriangle, ArrowUpRight, Filter, RefreshCcw } from 'lucide-react';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { DeliveryStatusBadge } from '@/components/events/delivery-status-badge';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { ScrollArea } from '@/components/ui/scroll-area';
import { publishToast } from '@/components/ui/use-toast';
import { useDeliveries } from '@/hooks/api/events/use-deliveries';
import { useReplayDlq } from '@/hooks/api/events/use-replay-dlq';
import { useRetryDelivery } from '@/hooks/api/events/use-retry-delivery';
import type { EventDeliveryItem, EventDeliveryStatus } from '@/types/notifications';
import { ApiError } from '@/lib/api';
import { cn } from '@/lib/utils';

const PAGE_SIZE = 25;
const EMPTY_DELIVERIES: EventDeliveryItem[] = [];

const STATUS_OPTIONS: Array<{ label: string; value: EventDeliveryStatus | 'all' }> = [
    { label: 'All statuses', value: 'all' },
    { label: 'Pending', value: 'pending' },
    { label: 'Delivered', value: 'success' },
    { label: 'Failed', value: 'failed' },
    { label: 'Dead letter', value: 'dead_letter' },
];

export function EventDeliveriesPage() {
    const [cursor, setCursor] = useState<string | null>(null);
    const [status, setStatus] = useState<'all' | EventDeliveryStatus>('all');
    const [search, setSearch] = useState('');
    const [endpoint, setEndpoint] = useState('');
    const [subscriptionId, setSubscriptionId] = useState('');
    const [dlqOnly, setDlqOnly] = useState(false);
    const [selectedDlq, setSelectedDlq] = useState<Set<number>>(new Set());
    const [detailItem, setDetailItem] = useState<EventDeliveryItem | null>(null);

    const deliveriesQuery = useDeliveries({
        cursor: cursor ?? undefined,
        per_page: PAGE_SIZE,
        status: status === 'all' ? undefined : status,
        endpoint: endpoint || undefined,
        subscription_id: subscriptionId ? Number(subscriptionId) : undefined,
        search: search || undefined,
        dlq_only: dlqOnly || undefined,
    });

    const retryDelivery = useRetryDelivery();
    const replayDlq = useReplayDlq();

    const items = deliveriesQuery.data?.items ?? EMPTY_DELIVERIES;
    const meta = deliveriesQuery.data?.meta;
    const nextCursor = typeof meta?.nextCursor === 'string' ? meta.nextCursor : null;
    const prevCursor = typeof meta?.prevCursor === 'string' ? meta.prevCursor : null;

    const dlqCount = useMemo(() => items.filter((item) => item.status === 'dead_letter').length, [items]);

    const toggleSelection = (id: number, checked: boolean) => {
        setSelectedDlq((prev) => {
            const next = new Set(prev);
            if (checked) {
                next.add(id);
            } else {
                next.delete(id);
            }
            return next;
        });
    };

    const resetFilters = () => {
        setCursor(null);
        setStatus('all');
        setSearch('');
        setEndpoint('');
        setSubscriptionId('');
        setDlqOnly(false);
        setSelectedDlq(new Set());
    };

    const handleRetry = async (item: EventDeliveryItem) => {
        try {
            await retryDelivery.mutateAsync({ id: item.id });
            publishToast({ title: 'Retry queued', description: `Delivery #${item.id} scheduled for retry.`, variant: 'success' });
        } catch (error) {
            const message = error instanceof ApiError ? error.message : 'Unable to retry delivery.';
            publishToast({ title: 'Retry failed', description: message, variant: 'destructive' });
        }
    };

    const handleReplay = async () => {
        if (selectedDlq.size === 0) {
            return;
        }

        try {
            await replayDlq.mutateAsync({ ids: Array.from(selectedDlq) });
            publishToast({ title: 'Replay queued', description: 'Selected dead-letter deliveries will be replayed shortly.', variant: 'success' });
            setSelectedDlq(new Set());
        } catch (error) {
            const message = error instanceof ApiError ? error.message : 'Unable to replay DLQ deliveries.';
            publishToast({ title: 'Replay failed', description: message, variant: 'destructive' });
        }
    };

    const formatRelative = useCallback((value?: string | null) => {
        if (!value) {
            return '--';
        }

        return formatDistanceToNow(new Date(value), { addSuffix: true });
    }, []);

    const openDetails = (item: EventDeliveryItem) => setDetailItem(item);
    const closeDetails = () => setDetailItem(null);

    const isLoading = deliveriesQuery.isLoading;
    const isRefreshing = deliveriesQuery.isFetching && !deliveriesQuery.isLoading;
    const hasError = deliveriesQuery.isError;
    const showEmpty = !isLoading && !hasError && items.length === 0;

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Event Deliveries</title>
            </Helmet>
            <WorkspaceBreadcrumbs />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Integrations</p>
                    <h1 className="text-2xl font-semibold text-foreground">Event deliveries</h1>
                    <p className="text-sm text-muted-foreground">
                        Inspect webhook attempts, retry failures, and replay dead-lettered payloads across your company.
                    </p>
                </div>
                <div className="flex flex-col gap-2 text-right text-sm">
                    <span className="text-muted-foreground">Dead-letter on page</span>
                    <span className="text-2xl font-semibold text-foreground">{dlqCount}</span>
                </div>
            </div>

            <Card className="border-border/70">
                <CardContent className="grid gap-4 py-6 lg:grid-cols-6">
                    <div className="space-y-2 lg:col-span-2">
                        <Label htmlFor="delivery-search">Search body, event, or error</Label>
                        <Input
                            id="delivery-search"
                            placeholder="invoice.overdue, 500, etc."
                            value={search}
                            onChange={(event) => {
                                setSearch(event.target.value);
                                setCursor(null);
                            }}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="delivery-endpoint">Endpoint contains</Label>
                        <Input
                            id="delivery-endpoint"
                            placeholder="https://hooks.example.com"
                            value={endpoint}
                            onChange={(event) => {
                                setEndpoint(event.target.value);
                                setCursor(null);
                            }}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="delivery-subscription">Subscription ID</Label>
                        <Input
                            id="delivery-subscription"
                            placeholder="1234"
                            inputMode="numeric"
                            value={subscriptionId}
                            onChange={(event) => {
                                setSubscriptionId(event.target.value.replace(/[^0-9]/g, ''));
                                setCursor(null);
                            }}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Status</Label>
                        <Select
                            value={status}
                            onValueChange={(value) => {
                                setStatus(value as typeof status);
                                setCursor(null);
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent align="start">
                                {STATUS_OPTIONS.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label className="inline-flex items-center gap-2 text-sm font-medium">
                            <Checkbox checked={dlqOnly} onCheckedChange={(checked) => {
                                setDlqOnly(Boolean(checked));
                                setCursor(null);
                            }} />
                            DLQ only
                        </Label>
                        <p className="text-xs text-muted-foreground">
                            Show only dead-lettered deliveries for replay.
                        </p>
                    </div>
                    <div className="flex flex-col gap-2 lg:items-end">
                        <Button
                            type="button"
                            variant="outline"
                            className="w-full lg:w-auto"
                            onClick={() => deliveriesQuery.refetch()}
                            disabled={isRefreshing}
                        >
                            <RefreshCcw className={cn('mr-2 h-4 w-4', isRefreshing && 'animate-spin')} /> Refresh
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            className="w-full lg:w-auto"
                            onClick={resetFilters}
                        >
                            <Filter className="mr-2 h-4 w-4" /> Reset
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <div className="flex flex-1 flex-col rounded-2xl border bg-card shadow-xs">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
                    <div className="text-sm text-muted-foreground">
                        {dlqOnly
                            ? `${items.length} dead-letter deliveries`
                            : `${items.length} deliveries in view`}
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            disabled={selectedDlq.size === 0 || replayDlq.isPending}
                            onClick={handleReplay}
                        >
                            Replay DLQ ({selectedDlq.size})
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={!prevCursor || isLoading}
                            onClick={() => setCursor(prevCursor)}
                        >
                            Prev
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={!nextCursor || isLoading}
                            onClick={() => setCursor(nextCursor)}
                        >
                            Next
                        </Button>
                    </div>
                </div>
                <div className="max-h-[60vh] overflow-auto">
                    <table className="min-w-full divide-y text-sm">
                        <thead className="bg-muted/40 text-left text-[11px] uppercase text-muted-foreground">
                            <tr>
                                <th className="w-12 px-4 py-3">DLQ</th>
                                <th className="px-4 py-3">Event</th>
                                <th className="px-4 py-3">Endpoint</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Attempts</th>
                                <th className="px-4 py-3">Latency</th>
                                <th className="px-4 py-3">Last error</th>
                                <th className="w-[160px] px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {isLoading &&
                                Array.from({ length: 6 }).map((_, index) => (
                                    <tr key={`delivery-skeleton-${index}`} className="animate-pulse">
                                        <td className="px-4 py-4">
                                            <Skeleton className="h-4 w-4" />
                                        </td>
                                        <td className="px-4 py-4">
                                            <Skeleton className="mb-2 h-4 w-32" />
                                            <Skeleton className="h-3 w-20" />
                                        </td>
                                        <td className="px-4 py-4">
                                            <Skeleton className="h-4 w-48" />
                                        </td>
                                        <td className="px-4 py-4">
                                            <Skeleton className="h-4 w-16" />
                                        </td>
                                        <td className="px-4 py-4">
                                            <Skeleton className="h-4 w-10" />
                                        </td>
                                        <td className="px-4 py-4">
                                            <Skeleton className="h-4 w-12" />
                                        </td>
                                        <td className="px-4 py-4">
                                            <Skeleton className="h-4 w-40" />
                                        </td>
                                        <td className="px-4 py-4">
                                            <Skeleton className="ml-auto h-8 w-20" />
                                        </td>
                                    </tr>
                                ))}

                            {!isLoading && items.map((item) => {
                                const isDlq = item.status === 'dead_letter';
                                const latencyLabel = item.latencyMs ? `${item.latencyMs} ms` : '--';
                                const attemptsLabel = item.maxAttempts
                                    ? `${item.attempts}/${item.maxAttempts}`
                                    : String(item.attempts);

                                return (
                                    <tr key={item.id} className={cn(isDlq && 'bg-destructive/5')}>
                                        <td className="px-4 py-4 align-top">
                                            {isDlq ? (
                                                <Checkbox
                                                    checked={selectedDlq.has(item.id)}
                                                    onCheckedChange={(checked) => toggleSelection(item.id, Boolean(checked))}
                                                    aria-label={`Select delivery ${item.id} for replay`}
                                                />
                                            ) : (
                                                <span className="text-xs text-muted-foreground">--</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-4 align-top">
                                            <div className="space-y-1">
                                                <p className="font-semibold text-foreground">{item.event}</p>
                                                <p className="text-xs text-muted-foreground">#{item.id}</p>
                                            </div>
                                        </td>
                                        <td className="px-4 py-4 align-top">
                                            <p className="text-sm text-muted-foreground break-all">{item.endpoint ?? 'N/A'}</p>
                                        </td>
                                        <td className="px-4 py-4 align-top">
                                            <DeliveryStatusBadge status={item.status} />
                                        </td>
                                        <td className="px-4 py-4 align-top">
                                            <p className="text-sm text-muted-foreground">{attemptsLabel}</p>
                                        </td>
                                        <td className="px-4 py-4 align-top">
                                            <p className="text-sm text-muted-foreground">{latencyLabel}</p>
                                        </td>
                                        <td className="px-4 py-4 align-top">
                                            <p className="text-xs text-muted-foreground line-clamp-2">{item.lastError ?? '--'}</p>
                                        </td>
                                        <td className="px-4 py-4 align-top">
                                            <div className="flex flex-col items-end gap-2">
                                                {(item.status === 'failed' || item.status === 'dead_letter') && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => handleRetry(item)}
                                                        disabled={retryDelivery.isPending}
                                                    >
                                                        Retry
                                                    </Button>
                                                )}
                                                <Sheet open={detailItem?.id === item.id} onOpenChange={(open) => (open ? openDetails(item) : closeDetails())}>
                                                    <SheetTrigger asChild>
                                                        <Button type="button" size="sm" variant="ghost">
                                                            Details
                                                        </Button>
                                                    </SheetTrigger>
                                                    <SheetContent className="sm:max-w-2xl">
                                                        <SheetHeader>
                                                            <SheetTitle>Delivery #{item.id}</SheetTitle>
                                                        </SheetHeader>
                                                        <ScrollArea className="flex-1 space-y-6 px-1 pb-6">
                                                            <section className="space-y-2">
                                                                <p className="text-xs uppercase tracking-wide text-muted-foreground">Overview</p>
                                                                <div className="grid grid-cols-2 gap-3 text-sm">
                                                                    <div>
                                                                        <p className="text-muted-foreground">Event</p>
                                                                        <p className="font-medium text-foreground">{item.event}</p>
                                                                    </div>
                                                                    <div>
                                                                        <p className="text-muted-foreground">Status</p>
                                                                        <DeliveryStatusBadge status={item.status} />
                                                                    </div>
                                                                    <div>
                                                                        <p className="text-muted-foreground">Attempts</p>
                                                                        <p className="font-medium text-foreground">{attemptsLabel}</p>
                                                                    </div>
                                                                    <div>
                                                                        <p className="text-muted-foreground">Dispatched</p>
                                                                        <p className="font-medium text-foreground">{formatRelative(item.dispatchedAt)}</p>
                                                                    </div>
                                                                    <div>
                                                                        <p className="text-muted-foreground">Delivered</p>
                                                                        <p className="font-medium text-foreground">{formatRelative(item.deliveredAt)}</p>
                                                                    </div>
                                                                    <div>
                                                                        <p className="text-muted-foreground">Dead lettered</p>
                                                                        <p className="font-medium text-foreground">{formatRelative(item.deadLetteredAt)}</p>
                                                                    </div>
                                                                </div>
                                                            </section>
                                                            <section className="space-y-2">
                                                                <p className="text-xs uppercase tracking-wide text-muted-foreground">Endpoint</p>
                                                                <p className="break-all rounded-md border bg-muted/40 p-3 text-sm text-foreground">{item.endpoint ?? 'N/A'}</p>
                                                            </section>
                                                            <section className="space-y-2">
                                                                <p className="text-xs uppercase tracking-wide text-muted-foreground">Request payload</p>
                                                                <pre className="max-h-64 overflow-auto rounded-md bg-muted/60 p-3 text-xs text-foreground">
                                                                    {JSON.stringify(item.payload ?? {}, null, 2)}
                                                                </pre>
                                                            </section>
                                                            <section className="space-y-2">
                                                                <p className="text-xs uppercase tracking-wide text-muted-foreground">Response (code {item.responseCode ?? 'N/A'})</p>
                                                                <pre className="max-h-64 overflow-auto rounded-md bg-muted/60 p-3 text-xs text-foreground">
                                                                    {item.responseBody ?? '--'}
                                                                </pre>
                                                            </section>
                                                            {item.lastError && (
                                                                <section className="space-y-2">
                                                                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Last error</p>
                                                                    <p className="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
                                                                        {item.lastError}
                                                                    </p>
                                                                </section>
                                                            )}
                                                        </ScrollArea>
                                                    </SheetContent>
                                                </Sheet>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}

                            {hasError && !isLoading && (
                                <tr>
                                    <td colSpan={8} className="px-4 py-16">
                                        <EmptyState
                                            title="Unable to load deliveries"
                                            description={deliveriesQuery.error?.message ?? 'Check your connection and try again.'}
                                            icon={<AlertTriangle className="h-8 w-8 text-destructive" />}
                                            ctaLabel="Retry"
                                            ctaProps={{ onClick: () => deliveriesQuery.refetch() }}
                                        />
                                    </td>
                                </tr>
                            )}

                            {showEmpty && (
                                <tr>
                                    <td colSpan={8} className="px-4 py-16">
                                        <EmptyState
                                            title="No deliveries match your filters"
                                            description="Adjust filters or wait for new events to dispatch."
                                            icon={<ArrowUpRight className="h-8 w-8" />}
                                        />
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}
