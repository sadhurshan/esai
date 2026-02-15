import { ChevronLeft, ChevronRight, RotateCcw } from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import type { CursorPaginationMeta } from '@/lib/pagination';
import { cn } from '@/lib/utils';
import type { WebhookDeliveryItem } from '@/types/admin';

export interface WebhookDeliveryTableProps {
    deliveries: WebhookDeliveryItem[];
    meta?: CursorPaginationMeta;
    isLoading?: boolean;
    onNextPage?: (cursor: string | null) => void;
    onPrevPage?: (cursor: string | null) => void;
    onRetry?: (delivery: WebhookDeliveryItem) => void;
    retryingDeliveryId?: string | null;
}

export function WebhookDeliveryTable({
    deliveries,
    meta,
    isLoading = false,
    onNextPage,
    onPrevPage,
    onRetry,
    retryingDeliveryId,
}: WebhookDeliveryTableProps) {
    if (isLoading) {
        return <LoadingTable />;
    }

    if (!deliveries.length) {
        return (
            <EmptyState
                icon={<RotateCcw className="h-10 w-10" aria-hidden />}
                title="No deliveries yet"
                description="Send a test event or trigger an integration workflow to populate delivery history."
            />
        );
    }

    const nextCursor = meta?.nextCursor ?? null;
    const prevCursor = meta?.prevCursor ?? null;

    return (
        <div className="overflow-hidden rounded-xl border">
            <table className="min-w-full divide-y divide-muted">
                <thead className="bg-muted/40 text-left text-xs tracking-wide text-muted-foreground uppercase">
                    <tr>
                        <th className="px-4 py-3 font-semibold">Event</th>
                        <th className="px-4 py-3 font-semibold">Status</th>
                        <th className="px-4 py-3 font-semibold">Attempts</th>
                        <th className="px-4 py-3 font-semibold">Last error</th>
                        <th className="px-4 py-3 font-semibold">Dispatched</th>
                        <th className="px-4 py-3 font-semibold">Delivered</th>
                        <th className="px-4 py-3 text-right font-semibold">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-muted bg-background text-sm">
                    {deliveries.map((delivery) => {
                        const statusBadge =
                            statusMap[delivery.status] ?? statusMap.default;
                        const retryable =
                            delivery.status === 'failed' ||
                            delivery.status === 'dead_letter';
                        const isRetrying = retryingDeliveryId === delivery.id;

                        return (
                            <tr key={delivery.id} className="hover:bg-muted/30">
                                <td className="px-4 py-3">
                                    <div className="flex flex-col gap-1">
                                        <span className="font-medium text-foreground">
                                            {delivery.event}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            #{delivery.id}
                                        </span>
                                    </div>
                                </td>
                                <td className="px-4 py-3">
                                    <Badge
                                        variant={statusBadge.variant}
                                        className={cn(
                                            'capitalize',
                                            statusBadge.className,
                                        )}
                                    >
                                        {statusBadge.label}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3">
                                    {delivery.attempts}
                                </td>
                                <td className="px-4 py-3 text-xs text-muted-foreground">
                                    {delivery.lastError
                                        ? truncate(delivery.lastError, 80)
                                        : '—'}
                                </td>
                                <td className="px-4 py-3 text-xs text-muted-foreground">
                                    {delivery.dispatchedAt
                                        ? formatDateTime(delivery.dispatchedAt)
                                        : '—'}
                                </td>
                                <td className="px-4 py-3 text-xs text-muted-foreground">
                                    {delivery.deliveredAt
                                        ? formatDateTime(delivery.deliveredAt)
                                        : '—'}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        disabled={
                                            !retryable || !onRetry || isRetrying
                                        }
                                        onClick={() => onRetry?.(delivery)}
                                    >
                                        {isRetrying ? 'Retrying...' : 'Retry'}
                                    </Button>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
            <div className="flex flex-col gap-3 border-t bg-muted/20 p-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                <span>{deliveries.length} deliveries</span>
                <div className="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={!prevCursor}
                        onClick={() => onPrevPage?.(prevCursor)}
                    >
                        <ChevronLeft className="mr-1 h-4 w-4" /> Previous
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={!nextCursor}
                        onClick={() => onNextPage?.(nextCursor)}
                    >
                        Next <ChevronRight className="ml-1 h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    );
}

function LoadingTable() {
    return (
        <div className="space-y-2 rounded-xl border p-4">
            {Array.from({ length: 4 }).map((_, index) => (
                <div
                    key={index}
                    className="grid gap-3 rounded-lg border bg-muted/20 p-3 md:grid-cols-6"
                >
                    <Skeleton className="h-4 w-32" />
                    <Skeleton className="h-4 w-20" />
                    <Skeleton className="h-4 w-10" />
                    <Skeleton className="h-4 w-full md:col-span-2" />
                    <Skeleton className="h-4 w-24" />
                </div>
            ))}
        </div>
    );
}

function formatDateTime(value: Date) {
    return new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(value);
}

function truncate(value: string, max = 80) {
    if (value.length <= max) {
        return value;
    }
    return `${value.slice(0, max)}...`;
}

const statusMap: Record<
    string,
    {
        label: string;
        variant: 'default' | 'secondary' | 'outline' | 'destructive';
        className?: string;
    }
> = {
    pending: { label: 'Pending', variant: 'outline' },
    dispatched: { label: 'Dispatched', variant: 'secondary' },
    delivered: { label: 'Delivered', variant: 'default' },
    failed: { label: 'Failed', variant: 'destructive' },
    dead_letter: { label: 'Dead letter', variant: 'destructive' },
    default: { label: 'Unknown', variant: 'outline' },
};
