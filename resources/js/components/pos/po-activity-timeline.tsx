import { AlertTriangle, CheckCircle2, Clock9, FileText, RefreshCcw, Send, XCircle } from 'lucide-react';
import { format, formatDistanceToNow } from 'date-fns';

import { cn } from '@/lib/utils';
import type { PurchaseOrderEvent } from '@/types/sourcing';
import { Skeleton } from '@/components/ui/skeleton';
import { Button } from '@/components/ui/button';

const EVENT_ICON_MAP: Array<{ matcher: (type?: string) => boolean; icon: React.ComponentType<{ className?: string }>; color: string }> = [
    { matcher: (type) => (type ?? '').includes('created'), icon: FileText, color: 'text-foreground' },
    { matcher: (type) => (type ?? '').includes('recalculated'), icon: RefreshCcw, color: 'text-foreground' },
    { matcher: (type) => (type ?? '').includes('delivery') && (type ?? '').includes('failed'), icon: AlertTriangle, color: 'text-destructive' },
    { matcher: (type) => (type ?? '').includes('delivery') || (type ?? '').includes('sent'), icon: Send, color: 'text-primary' },
    { matcher: (type) => (type ?? '').includes('ack') || (type ?? '').includes('acknowledge'), icon: CheckCircle2, color: 'text-emerald-600' },
    { matcher: (type) => (type ?? '').includes('decline') || (type ?? '').includes('reject'), icon: XCircle, color: 'text-destructive' },
    { matcher: (type) => (type ?? '').includes('invoice'), icon: FileText, color: 'text-sky-600' },
];

function resolveIcon(type?: string) {
    const match = EVENT_ICON_MAP.find(({ matcher }) => matcher(type));
    return match ?? { icon: Clock9, color: 'text-muted-foreground' };
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

export interface PoActivityTimelineProps {
    events?: PurchaseOrderEvent[];
    isLoading?: boolean;
    error?: string | null;
    onRetry?: () => void;
}

export function PoActivityTimeline({ events, isLoading, error, onRetry }: PoActivityTimelineProps) {
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
                <p className="text-muted-foreground">Unable to load the latest activity.</p>
                <Button variant="outline" size="sm" className="w-fit" onClick={onRetry}>
                    Retry
                </Button>
            </div>
        );
    }

    if (!events || events.length === 0) {
        return <p className="text-sm text-muted-foreground">No timeline entries yet.</p>;
    }

    const sorted = [...events].sort((left, right) => {
        const leftDate = new Date(left.occurredAt ?? left.createdAt ?? 0).getTime();
        const rightDate = new Date(right.occurredAt ?? right.createdAt ?? 0).getTime();
        return rightDate - leftDate;
    });

    return (
        <ol className="relative space-y-6 border-l border-border/50 pl-6">
            {sorted.map((event) => {
                const timestamp = formatTimestamp(event.occurredAt ?? event.createdAt);
                const { icon: Icon, color } = resolveIcon(event.type);
                return (
                    <li key={event.id} className="space-y-1">
                        <span
                            className={cn(
                                'absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full border bg-background',
                                color,
                            )}
                        >
                            <Icon className="h-3.5 w-3.5" />
                        </span>
                        <div className="text-sm font-semibold text-foreground">{event.summary ?? event.type ?? 'Event'}</div>
                        {event.description ? (
                            <p className="text-sm text-muted-foreground">{event.description}</p>
                        ) : null}
                        {event.actor?.name ? (
                            <p className="text-xs text-muted-foreground">By {event.actor.name}</p>
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