import { formatDistanceToNow } from 'date-fns';
import { Inbox, Mail, RefreshCcw } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { useNavigate } from 'react-router-dom';

import { WorkspaceBreadcrumbs } from '@/components/breadcrumbs';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { publishToast } from '@/components/ui/use-toast';
import { useMarkRead } from '@/hooks/api/notifications/use-mark-read';
import { useNotifications } from '@/hooks/api/notifications/use-notifications';
import { ApiError } from '@/lib/api';
import { cn } from '@/lib/utils';
import type { NotificationListItem } from '@/types/notifications';

const PER_PAGE = 25;
const EMPTY_NOTIFICATIONS: NotificationListItem[] = [];
const LINK_META_KEYS: Array<keyof NotificationListItem['meta']> = [
    'href',
    'url',
    'route',
    'path',
];

type StatusFilter = 'all' | 'read' | 'unread';

export function NotificationCenterPage() {
    const navigate = useNavigate();
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');
    const [searchTerm, setSearchTerm] = useState('');
    const [page, setPage] = useState(1);
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());

    const notificationsQuery = useNotifications({
        status: statusFilter === 'all' ? undefined : statusFilter,
        page,
        per_page: PER_PAGE,
    });
    const markRead = useMarkRead();

    const items = notificationsQuery.data?.items ?? EMPTY_NOTIFICATIONS;
    const meta = notificationsQuery.data?.meta;
    const unreadCount = meta?.unreadCount ?? 0;
    const currentPage = meta?.currentPage ?? page;
    const totalPages = meta?.lastPage ?? currentPage;

    const filteredItems = useMemo(() => {
        if (!searchTerm.trim()) {
            return items;
        }

        const term = searchTerm.trim().toLowerCase();
        return items.filter((item) => {
            return (
                item.title.toLowerCase().includes(term) ||
                item.body.toLowerCase().includes(term) ||
                item.eventType.toLowerCase().includes(term) ||
                (item.entityType?.toLowerCase().includes(term) ?? false)
            );
        });
    }, [items, searchTerm]);

    const visibleSelectedIds = useMemo(() => {
        if (selectedIds.size === 0) {
            return selectedIds;
        }

        const allowed = new Set(filteredItems.map((item) => item.id));
        const next = new Set<number>();
        selectedIds.forEach((id) => {
            if (allowed.has(id)) {
                next.add(id);
            }
        });
        return next;
    }, [filteredItems, selectedIds]);

    const resolveLink = useCallback(
        (item: NotificationListItem): string | null => {
            for (const key of LINK_META_KEYS) {
                const raw = item.meta?.[key];
                if (typeof raw === 'string' && raw.length > 0) {
                    return raw;
                }
            }

            const fallback = item.meta?.link;
            if (typeof fallback === 'string' && fallback.length > 0) {
                return fallback;
            }

            return null;
        },
        [],
    );

    const handleRowNavigate = useCallback(
        (item: NotificationListItem) => {
            const link = resolveLink(item);
            if (!link) {
                return;
            }

            if (/^https?:\/\//i.test(link)) {
                window.open(link, '_blank', 'noopener,noreferrer');
                return;
            }

            navigate(link);
        },
        [navigate, resolveLink],
    );

    const formatTimestamp = useCallback((value?: string | null) => {
        if (!value) {
            return '--';
        }

        return formatDistanceToNow(new Date(value), { addSuffix: true });
    }, []);

    const formatEventType = useCallback((value?: string) => {
        if (!value) {
            return 'General';
        }

        return value
            .replace(/([a-z])([A-Z])/g, '$1 $2')
            .replace(/[_-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }, []);

    const toggleSelection = (id: number, checked: boolean) => {
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (checked) {
                next.add(id);
            } else {
                next.delete(id);
            }
            return next;
        });
    };

    const allSelected =
        filteredItems.length > 0 &&
        filteredItems.every((item) => visibleSelectedIds.has(item.id));
    const indeterminate = visibleSelectedIds.size > 0 && !allSelected;

    const handleSelectAll = (checked: boolean) => {
        if (checked) {
            setSelectedIds(new Set(filteredItems.map((item) => item.id)));
            return;
        }

        setSelectedIds(new Set());
    };

    const handleBulkMarkRead = async () => {
        if (visibleSelectedIds.size === 0) {
            return;
        }

        try {
            await markRead.mutateAsync({ ids: Array.from(visibleSelectedIds) });
            publishToast({
                title: 'Notifications updated',
                description: 'Selected notifications marked as read.',
                variant: 'success',
            });
            setSelectedIds(new Set());
        } catch (error) {
            const message =
                error instanceof ApiError
                    ? error.message
                    : 'Unable to mark notifications as read.';
            publishToast({
                title: 'Request failed',
                description: message,
                variant: 'destructive',
            });
        }
    };

    const handleMarkSingle = async (id: number) => {
        try {
            await markRead.mutateAsync({ ids: [id] });
            publishToast({
                title: 'Notification marked as read',
                variant: 'success',
            });
            setSelectedIds((prev) => {
                const next = new Set(prev);
                next.delete(id);
                return next;
            });
        } catch (error) {
            const message =
                error instanceof ApiError
                    ? error.message
                    : 'Unable to mark notification as read.';
            publishToast({
                title: 'Request failed',
                description: message,
                variant: 'destructive',
            });
        }
    };

    const isLoading = notificationsQuery.isLoading;
    const isRefreshing =
        notificationsQuery.isFetching && !notificationsQuery.isLoading;
    const showEmpty = !isLoading && filteredItems.length === 0;

    return (
        <div className="flex flex-1 flex-col gap-6">
            <Helmet>
                <title>Notifications Center</title>
            </Helmet>
            <WorkspaceBreadcrumbs />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-1">
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        Engagement
                    </p>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Notification center
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Review alerts from RFQs, quotes, purchase orders,
                        receiving, billing, and system events in one place.
                    </p>
                </div>
                <div className="flex flex-col gap-2 text-right text-sm">
                    <span className="text-muted-foreground">Unread</span>
                    <span className="text-2xl font-semibold text-foreground">
                        {unreadCount}
                    </span>
                </div>
            </div>

            <Card className="border-border/70">
                <CardContent className="grid gap-4 py-6 md:grid-cols-4">
                    <div className="space-y-2 md:col-span-2">
                        <Label htmlFor="notification-search">Search</Label>
                        <Input
                            id="notification-search"
                            placeholder="Search title, message, or entity"
                            value={searchTerm}
                            onChange={(event) =>
                                setSearchTerm(event.target.value)
                            }
                            autoComplete="off"
                        />
                        <p className="text-xs text-muted-foreground">
                            Search applies to the current page of notifications.
                        </p>
                    </div>
                    <div className="space-y-2">
                        <Label>Status</Label>
                        <Select
                            value={statusFilter}
                            onValueChange={(value) => {
                                setStatusFilter(value as StatusFilter);
                                setPage(1);
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="All" />
                            </SelectTrigger>
                            <SelectContent align="start">
                                <SelectItem value="all">All</SelectItem>
                                <SelectItem value="unread">
                                    Unread only
                                </SelectItem>
                                <SelectItem value="read">Read only</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label
                            className="sr-only"
                            htmlFor="notification-refresh"
                        >
                            Refresh
                        </Label>
                        <Button
                            id="notification-refresh"
                            type="button"
                            variant="outline"
                            className="w-full"
                            onClick={() => notificationsQuery.refetch()}
                            disabled={isRefreshing}
                        >
                            <RefreshCcw
                                className={cn(
                                    'mr-2 h-4 w-4',
                                    isRefreshing && 'animate-spin',
                                )}
                            />{' '}
                            Refresh
                        </Button>
                        <Button
                            type="button"
                            variant="default"
                            className="w-full"
                            onClick={handleBulkMarkRead}
                            disabled={
                                visibleSelectedIds.size === 0 ||
                                markRead.isPending
                            }
                        >
                            Mark selected read
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <div className="flex flex-1 flex-col rounded-2xl border bg-card shadow-xs">
                <div className="flex items-center justify-between border-b px-4 py-3">
                    <div className="flex items-center gap-3 text-sm text-muted-foreground">
                        <Checkbox
                            checked={
                                allSelected
                                    ? true
                                    : indeterminate
                                      ? 'indeterminate'
                                      : false
                            }
                            onCheckedChange={(checked) =>
                                handleSelectAll(Boolean(checked))
                            }
                            aria-label="Select all notifications"
                        />
                        <span className="text-foreground">
                            {visibleSelectedIds.size > 0
                                ? `${visibleSelectedIds.size} selected`
                                : `${filteredItems.length} on this page`}
                        </span>
                    </div>
                    <div className="text-xs text-muted-foreground">
                        Page {currentPage} of {Math.max(totalPages, 1)}
                    </div>
                </div>
                <div className="max-h-[60vh] overflow-auto">
                    <table className="min-w-full divide-y">
                        <thead className="bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                            <tr>
                                <th className="w-12 px-4 py-3">Select</th>
                                <th className="min-w-[220px] px-4 py-3">
                                    Notification
                                </th>
                                <th className="px-4 py-3">Entity</th>
                                <th className="px-4 py-3">Channel</th>
                                <th className="px-4 py-3">Sent</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="w-[160px] px-4 py-3 text-right">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y text-sm">
                            {isLoading &&
                                Array.from({ length: 6 }).map((_, index) => (
                                    <tr
                                        key={`notification-skeleton-${index}`}
                                        className="animate-pulse"
                                    >
                                        <td className="px-4 py-4">
                                            <Skeleton className="h-4 w-4" />
                                        </td>
                                        <td className="px-4 py-4">
                                            <Skeleton className="mb-2 h-4 w-3/4" />
                                            <Skeleton className="h-3 w-1/2" />
                                        </td>
                                        <td className="px-4 py-4">
                                            <Skeleton className="h-4 w-1/2" />
                                        </td>
                                        <td className="px-4 py-4">
                                            <Skeleton className="h-4 w-16" />
                                        </td>
                                        <td className="px-4 py-4">
                                            <Skeleton className="h-4 w-20" />
                                        </td>
                                        <td className="px-4 py-4">
                                            <Skeleton className="h-4 w-16" />
                                        </td>
                                        <td className="px-4 py-4">
                                            <Skeleton className="ml-auto h-8 w-24" />
                                        </td>
                                    </tr>
                                ))}

                            {!isLoading &&
                                filteredItems.map((item) => {
                                    const link = resolveLink(item);
                                    const isSelected = selectedIds.has(item.id);
                                    const isUnread = !item.readAt;
                                    const channelLabel =
                                        item.channel === 'both'
                                            ? 'Push + Email'
                                            : item.channel;
                                    const entityLabel = item.entityType
                                        ? `${item.entityType}${item.entityId ? ` #${item.entityId}` : ''}`
                                        : 'General';

                                    return (
                                        <tr
                                            key={item.id}
                                            className={cn(
                                                isUnread && 'bg-primary/5',
                                            )}
                                        >
                                            <td className="px-4 py-4 align-top">
                                                <Checkbox
                                                    checked={isSelected}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggleSelection(
                                                            item.id,
                                                            Boolean(checked),
                                                        )
                                                    }
                                                    aria-label={`Select notification ${item.title}`}
                                                />
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <div className="space-y-1">
                                                    <div className="flex items-center gap-2">
                                                        <p className="font-semibold text-foreground">
                                                            {item.title}
                                                        </p>
                                                        <Badge
                                                            variant="secondary"
                                                            className="text-[10px] tracking-wider uppercase"
                                                        >
                                                            {formatEventType(
                                                                item.eventType,
                                                            )}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-sm text-muted-foreground">
                                                        {item.body}
                                                    </p>
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <p className="text-sm font-medium text-foreground">
                                                    {entityLabel}
                                                </p>
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <span className="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                                                    {item.channel !==
                                                        'push' && (
                                                        <Mail
                                                            className="h-3.5 w-3.5"
                                                            aria-hidden="true"
                                                        />
                                                    )}{' '}
                                                    {channelLabel}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <p className="text-sm text-muted-foreground">
                                                    {formatTimestamp(
                                                        item.createdAt,
                                                    )}
                                                </p>
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <Badge
                                                    variant={
                                                        isUnread
                                                            ? 'destructive'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {isUnread
                                                        ? 'Unread'
                                                        : 'Read'}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-4 align-top">
                                                <div className="flex flex-col items-end gap-2">
                                                    {link && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            className="w-full"
                                                            onClick={() =>
                                                                handleRowNavigate(
                                                                    item,
                                                                )
                                                            }
                                                        >
                                                            Open
                                                        </Button>
                                                    )}
                                                    {isUnread && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() =>
                                                                handleMarkSingle(
                                                                    item.id,
                                                                )
                                                            }
                                                            disabled={
                                                                markRead.isPending
                                                            }
                                                        >
                                                            Mark read
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}

                            {showEmpty && (
                                <tr>
                                    <td colSpan={7} className="px-4 py-16">
                                        <EmptyState
                                            title="No notifications to show"
                                            description="Try switching filters or wait for new events to process."
                                            icon={<Inbox className="h-8 w-8" />}
                                        />
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t px-4 py-3 text-sm">
                    <div className="text-muted-foreground">
                        Showing up to {PER_PAGE} per page
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                setPage((prev) => Math.max(prev - 1, 1))
                            }
                            disabled={currentPage <= 1 || isLoading}
                        >
                            Previous
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => setPage((prev) => prev + 1)}
                            disabled={currentPage >= totalPages || isLoading}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}
