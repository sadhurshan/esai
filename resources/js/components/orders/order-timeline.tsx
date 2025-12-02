import { Box, CheckCircle2, PackageCheck, PackagePlus, Truck, XCircle } from 'lucide-react';
import { format, formatDistanceToNow } from 'date-fns';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { SalesOrderTimelineEntry } from '@/types/orders';

interface OrderTimelineProps {
    entries?: SalesOrderTimelineEntry[];
    isLoading?: boolean;
    error?: string | null;
    onRetry?: () => void;
    emptyLabel?: string;
}

const ICON_PRESETS: Array<{
    matcher: (entry: SalesOrderTimelineEntry) => boolean;
    icon: React.ComponentType<{ className?: string }>;
    color: string;
}> = [
    { matcher: (entry) => entry.type === 'acknowledged', icon: CheckCircle2, color: 'text-emerald-600' },
    { matcher: (entry) => entry.type === 'shipment' && (entry.metadata?.status === 'delivered'), icon: PackageCheck, color: 'text-emerald-600' },
    { matcher: (entry) => entry.type === 'shipment', icon: Truck, color: 'text-sky-600' },
    { matcher: (entry) => entry.type === 'status_change' && (entry.metadata?.status === 'cancelled'), icon: XCircle, color: 'text-destructive' },
    { matcher: (entry) => entry.type === 'status_change', icon: PackagePlus, color: 'text-indigo-600' },
];

function resolveIcon(entry: SalesOrderTimelineEntry) {
    return ICON_PRESETS.find(({ matcher }) => matcher(entry)) ?? { icon: Box, color: 'text-muted-foreground' };
}

function formatTimestamp(value?: string | null): { relative: string; absolute: string } | null {
    if (!value) {
        return null;
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return {
        relative: formatDistanceToNow(date, { addSuffix: true }),
        absolute: format(date, 'PPpp'),
    };
}

export function OrderTimeline({ entries, isLoading, error, onRetry, emptyLabel }: OrderTimelineProps) {
    if (isLoading) {
        return (
            <div className="space-y-4">
                {Array.from({ length: 4 }).map((_, index) => (
                    <div key={index} className="flex items-start gap-4">
                        <Skeleton className="h-10 w-10 rounded-full" />
                        <div className="flex-1 space-y-2">
                            <Skeleton className="h-4 w-1/3" />
                            <Skeleton className="h-3 w-1/2" />
                        </div>
                    </div>
                ))}
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex flex-col gap-3 text-sm">
                <p className="text-muted-foreground">Unable to load order events.</p>
                <Button variant="outline" size="sm" className="w-fit" onClick={onRetry}>
                    Retry
                </Button>
            </div>
        );
    }

    if (!entries || entries.length === 0) {
        return <p className="text-sm text-muted-foreground">{emptyLabel ?? 'No events captured yet.'}</p>;
    }

    const sorted = [...entries].sort((left, right) => {
        const leftDate = new Date(left.occurredAt ?? 0).getTime();
        const rightDate = new Date(right.occurredAt ?? 0).getTime();
        return rightDate - leftDate;
    });

    return (
        <ol className="relative space-y-6 border-l border-border/50 pl-6">
            {sorted.map((entry) => {
                const timestamp = formatTimestamp(entry.occurredAt);
                const { icon: Icon, color } = resolveIcon(entry);
                return (
                    <li key={entry.id} className="space-y-1">
                        <span
                            className={cn(
                                'absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full border bg-background',
                                color,
                            )}
                        >
                            <Icon className="h-3.5 w-3.5" />
                        </span>
                        <div className="text-sm font-semibold text-foreground">{entry.summary ?? 'Order event'}</div>
                        {entry.description ? <p className="text-sm text-muted-foreground">{entry.description}</p> : null}
                        {entry.actor?.name ? (
                            <p className="text-xs text-muted-foreground">By {entry.actor.name}</p>
                        ) : null}
                        {timestamp ? (
                            <p className="text-xs text-muted-foreground">
                                {timestamp.relative} • {timestamp.absolute}
                            </p>
                        ) : null}
                    </li>
                );
            })}
        </ol>
    );
}
