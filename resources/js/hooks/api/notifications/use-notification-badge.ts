import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, buildQuery, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';

interface NotificationBadgeResponse {
    meta?: {
        unread_count?: number;
        unreadCount?: number;
    } | null;
}

const DEFAULT_PARAMS = {
    status: 'unread',
    per_page: 1,
};

export interface NotificationBadgeResult {
    unreadCount: number;
}

export function useNotificationBadge(): UseQueryResult<NotificationBadgeResult, ApiError> {
    return useQuery<NotificationBadgeResponse, ApiError, NotificationBadgeResult>({
        queryKey: queryKeys.notifications.badge(),
        queryFn: async () => {
            const query = buildQuery(DEFAULT_PARAMS);
            return (await api.get<NotificationBadgeResponse>(`/notifications${query}`)) as unknown as NotificationBadgeResponse;
        },
        select: (response) => ({
            unreadCount: response.meta?.unread_count ?? response.meta?.unreadCount ?? 0,
        }),
        staleTime: 10_000,
    });
}
