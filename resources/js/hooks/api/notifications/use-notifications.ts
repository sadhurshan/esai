import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, buildQuery, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type {
    NotificationListFilters,
    NotificationListItem,
    NotificationListMeta,
    NotificationListResult,
} from '@/types/notifications';

interface NotificationResponseItem {
    id: number;
    event_type: string;
    title: string;
    body: string;
    entity_type?: string | null;
    entity_id?: number | string | null;
    channel: 'push' | 'email' | 'both';
    meta?: Record<string, unknown> | null;
    read_at?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
}

interface NotificationIndexResponse {
    data: NotificationResponseItem[];
    meta?: {
        total?: number;
        per_page?: number;
        perPage?: number;
        current_page?: number;
        currentPage?: number;
        last_page?: number;
        lastPage?: number;
        unread_count?: number;
        unreadCount?: number;
    } | null;
}

const mapNotification = (payload: NotificationResponseItem): NotificationListItem => ({
    id: payload.id,
    eventType: payload.event_type,
    title: payload.title,
    body: payload.body,
    entityType: payload.entity_type,
    entityId: payload.entity_id,
    channel: payload.channel,
    meta: payload.meta ?? {},
    readAt: payload.read_at,
    createdAt: payload.created_at,
    updatedAt: payload.updated_at,
});

const mapMeta = (meta?: NotificationIndexResponse['meta']): NotificationListMeta => ({
    total: meta?.total,
    perPage: meta?.per_page ?? meta?.perPage,
    currentPage: meta?.current_page ?? meta?.currentPage,
    lastPage: meta?.last_page ?? meta?.lastPage,
    unreadCount: meta?.unread_count ?? meta?.unreadCount,
});

export type UseNotificationsParams = NotificationListFilters;

export function useNotifications(
    params: UseNotificationsParams = {},
): UseQueryResult<NotificationListResult, ApiError> {
    const queryParams: Record<string, unknown> = { ...params };

    return useQuery<NotificationIndexResponse, ApiError, NotificationListResult>({
        queryKey: queryKeys.notifications.list(queryParams),
        queryFn: async () => {
            const query = buildQuery(queryParams);
            return (await api.get<NotificationIndexResponse>(
                `/notifications${query}`,
            )) as unknown as NotificationIndexResponse;
        },
        select: (response) => ({
            items: response.data.map(mapNotification),
            meta: mapMeta(response.meta),
        }),
        placeholderData: keepPreviousData,
        staleTime: 15_000,
    });
}
