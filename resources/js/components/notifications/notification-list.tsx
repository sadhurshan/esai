import { useCallback, useState } from 'react';
import { formatDistanceToNow } from 'date-fns';
import { AlertTriangle, BellOff, ExternalLink, Mail, RefreshCw, CheckCheck } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Skeleton } from '@/components/ui/skeleton';
import { publishToast } from '@/components/ui/use-toast';
import { useNotifications } from '@/hooks/api/notifications/use-notifications';
import { useMarkRead } from '@/hooks/api/notifications/use-mark-read';
import type { NotificationListItem } from '@/types/notifications';
import { ApiError } from '@/lib/api';
import { cn } from '@/lib/utils';

const LINK_KEYS: Array<keyof NotificationListItem['meta']> = ['href', 'url', 'path', 'route'];

type NotificationFilter = 'all' | 'unread';

const FILTER_OPTIONS: Array<{ label: string; value: NotificationFilter }> = [
    { label: 'All', value: 'all' },
    { label: 'Unread', value: 'unread' },
];

interface NotificationListProps {
    className?: string;
    onNavigate?: (href: string) => void;
    onDismiss?: () => void;
}

export function NotificationList({ className, onNavigate, onDismiss }: NotificationListProps) {
    const [filter, setFilter] = useState<NotificationFilter>('all');
    const notificationsQuery = useNotifications({
        status: filter === 'unread' ? 'unread' : undefined,
        per_page: 20,
    });
    const markRead = useMarkRead();
    const [pendingIds, setPendingIds] = useState<Set<number>>(new Set());

    const unreadCount = notificationsQuery.data?.meta.unreadCount ?? 0;
    const items = notificationsQuery.data?.items ?? [];

    const filterLabel = filter === 'unread' ? 'Only unread notifications' : 'All notifications';

    const setPending = useCallback((id: number, value: boolean) => {
        setPendingIds((prev) => {
            const next = new Set(prev);
            if (value) {
                next.add(id);
            } else {
                next.delete(id);
            }
            return next;
        });
    }, []);

    const resolveLink = useCallback((item: NotificationListItem): string | null => {
        for (const key of LINK_KEYS) {
            const raw = item.meta?.[key];
            if (typeof raw === 'string' && raw.length > 0) {
                return raw;
            }
        }

        const metaLink = item.meta?.link;
        if (typeof metaLink === 'string' && metaLink.length > 0) {
            return metaLink;
        }

        return null;
    }, []);

    const formatTimestamp = useCallback((value?: string | null) => {
        if (!value) {
            return '--';
        }

        return formatDistanceToNow(new Date(value), { addSuffix: true });
    }, []);

    const formatEventType = useCallback((value: string) => {
        return value
            .replace(/([a-z])([A-Z])/g, '$1 $2')
            .replace(/[_-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }, []);

    const handleMarkRead = useCallback(
        async (id: number) => {
            setPending(id, true);
            try {
                await markRead.mutateAsync({ ids: [id] });
                publishToast({
                    title: 'Notification marked as read',
                    variant: 'success',
                });
            } catch (error) {
                const message = error instanceof ApiError ? error.message : 'Failed to update notification.';
                publishToast({
                    title: 'Unable to update notification',
                    description: message,
                    variant: 'destructive',
                });
            } finally {
                setPending(id, false);
            }
        },
        [markRead, setPending],
    );

    const handleNavigate = useCallback(
        (item: NotificationListItem) => {
            const link = resolveLink(item);
            if (!link) {
                return;
            }

            onNavigate?.(link);
            onDismiss?.();
        },
        [onDismiss, onNavigate, resolveLink],
    );

    const showSkeleton = notificationsQuery.isLoading;
    const showEmpty = !showSkeleton && !notificationsQuery.isError && items.length === 0;
    const isRefreshing = notificationsQuery.isFetching && !notificationsQuery.isLoading;

    return (
        <div className={cn('flex min-h-0 flex-1 flex-col gap-3', className)}>
            <div className="flex flex-col gap-3 px-4">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm font-medium">Notifications</p>
                        <p className="text-xs text-muted-foreground">{filterLabel}</p>
                    </div>
                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                        <span>Unread:</span>
                        <span className="font-semibold">{unreadCount}</span>
                    </div>
                </div>
                <div className="flex items-center justify-between gap-2">
                    <div className="inline-flex rounded-full border bg-background p-1 text-xs">
                        {FILTER_OPTIONS.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                className={cn(
                                    'rounded-full px-3 py-1 font-medium transition',
                                    option.value === filter
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                                onClick={() => setFilter(option.value)}
                                aria-pressed={option.value === filter}
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => notificationsQuery.refetch()}
                        disabled={isRefreshing}
                        className="shrink-0"
                        type="button"
                    >
                        <RefreshCw className={cn('h-4 w-4', isRefreshing && 'animate-spin')} />
                        <span className="sr-only">Refresh</span>
                    </Button>
                </div>
            </div>

            {notificationsQuery.isError ? (
                <div className="flex flex-1 flex-col items-center justify-center gap-3 px-6 text-center">
                    <AlertTriangle className="h-6 w-6 text-destructive" aria-hidden="true" />
                    <div className="space-y-1">
                        <p className="text-sm font-semibold">Unable to load notifications</p>
                        <p className="text-xs text-muted-foreground">
                            {notificationsQuery.error?.message ?? 'Please try again in a few seconds.'}
                        </p>
                    </div>
                    <Button onClick={() => notificationsQuery.refetch()} size="sm" type="button">
                        Try again
                    </Button>
                </div>
            ) : (
                <ScrollArea className="flex-1 px-4 pb-4">
                    {showSkeleton ? (
                        <div className="space-y-3">
                            {Array.from({ length: 5 }).map((_, index) => (
                                <div key={`skeleton-${index}`} className="rounded-xl border bg-card/40 p-4">
                                    <Skeleton className="mb-2 h-3 w-32" />
                                    <Skeleton className="mb-1 h-4 w-5/6" />
                                    <Skeleton className="h-3 w-2/3" />
                                </div>
                            ))}
                        </div>
                    ) : showEmpty ? (
                        <div className="flex flex-col items-center gap-3 rounded-xl border bg-muted/30 px-6 py-10 text-center">
                            <BellOff className="h-8 w-8 text-muted-foreground" aria-hidden="true" />
                            <div className="space-y-1">
                                <p className="text-sm font-semibold">You&apos;re all caught up</p>
                                <p className="text-xs text-muted-foreground">
                                    New alerts will show up here as soon as they arrive.
                                </p>
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {items.map((item) => {
                                const link = resolveLink(item);
                                const pending = pendingIds.has(item.id);
                                const readableEvent = formatEventType(item.eventType);
                                const sentAt = formatTimestamp(item.createdAt);
                                const viaEmail = item.channel === 'email' || item.channel === 'both';
                                const unread = !item.readAt;

                                return (
                                    <article
                                        key={item.id}
                                        className={cn(
                                            'rounded-2xl border bg-card/60 p-4 transition',
                                            unread ? 'border-primary/50 shadow-sm' : 'border-border',
                                        )}
                                    >
                                        <div className="flex items-start gap-3">
                                            <span
                                                className={cn(
                                                    'mt-1 inline-flex h-2.5 w-2.5 rounded-full',
                                                    unread ? 'bg-primary' : 'bg-muted-foreground/40',
                                                )}
                                                aria-hidden="true"
                                            />
                                            <div className="flex-1 space-y-2">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="text-sm font-semibold leading-snug">{item.title}</p>
                                                    {item.eventType && (
                                                        <Badge variant="secondary" className="text-[10px] uppercase tracking-wide">
                                                            {readableEvent}
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="text-sm text-muted-foreground">{item.body}</p>
                                                <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                                    <span>{sentAt}</span>
                                                    {item.entityType && (
                                                        <span className="rounded-full bg-muted px-2 py-0.5 font-medium uppercase tracking-wide">
                                                            {item.entityType}
                                                        </span>
                                                    )}
                                                    {viaEmail && (
                                                        <span className="inline-flex items-center gap-1">
                                                            <Mail className="h-3 w-3" aria-hidden="true" /> Email
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="flex flex-wrap gap-2">
                                                    {link && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            className="h-8 px-3 text-xs"
                                                            onClick={() => handleNavigate(item)}
                                                            type="button"
                                                        >
                                                            <ExternalLink className="h-3.5 w-3.5" /> View details
                                                        </Button>
                                                    )}
                                                    {!item.readAt && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-8 px-3 text-xs"
                                                            onClick={() => handleMarkRead(item.id)}
                                                            disabled={pending}
                                                            type="button"
                                                        >
                                                            <CheckCheck className="h-3.5 w-3.5" />
                                                            {pending ? 'Updating...' : 'Mark read'}
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    )}
                </ScrollArea>
            )}
        </div>
    );
}
