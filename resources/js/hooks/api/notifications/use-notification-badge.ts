import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, buildQuery, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';

interface NotificationBadgeResponse {
    meta?: {
        unread_count?: number;
        unreadCount?: number;
        envelope?: {
            unread_count?: number;
            unreadCount?: number;
        } | null;
    } | null;
}

const DEFAULT_PARAMS = {
    status: 'unread',
    per_page: 1,
};

export interface NotificationBadgeResult {
    unreadCount: number;
}

export function useNotificationBadge(): UseQueryResult<
    NotificationBadgeResult,
    ApiError
> {
    return useQuery<
        NotificationBadgeResponse,
        ApiError,
        NotificationBadgeResult
    >({
        queryKey: queryKeys.notifications.badge(),
        queryFn: async () => {
            const query = buildQuery(DEFAULT_PARAMS);
            return (await api.get<NotificationBadgeResponse>(
                `/notifications${query}`,
            )) as unknown as NotificationBadgeResponse;
        },
        select: (response) => {
            const meta = response.meta ?? undefined;
            const envelope =
                meta && meta.envelope && typeof meta.envelope === 'object'
                    ? meta.envelope
                    : undefined;

            const unreadCount =
                meta?.unread_count ??
                meta?.unreadCount ??
                (envelope && typeof envelope.unread_count === 'number'
                    ? envelope.unread_count
                    : undefined) ??
                (envelope && typeof envelope.unreadCount === 'number'
                    ? envelope.unreadCount
                    : undefined) ??
                0;

            return { unreadCount };
        },
        staleTime: 10_000,
    });
}
