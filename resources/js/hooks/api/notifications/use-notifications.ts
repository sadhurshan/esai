import {
    keepPreviousData,
    useQuery,
    type UseQueryResult,
} from '@tanstack/react-query';

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

type MetaRecord = Record<string, unknown>;

interface NotificationIndexMeta extends MetaRecord {
    total?: number;
    per_page?: number;
    perPage?: number;
    current_page?: number;
    currentPage?: number;
    last_page?: number;
    lastPage?: number;
    unread_count?: number;
    unreadCount?: number;
    data?: MetaRecord | null;
    envelope?: MetaRecord | null;
}

interface NotificationIndexResponse {
    items?: NotificationResponseItem[];
    data?: NotificationResponseItem[];
    meta?: NotificationIndexMeta | null;
}

const toRecord = (value: unknown): MetaRecord | undefined =>
    typeof value === 'object' && value !== null && !Array.isArray(value)
        ? (value as MetaRecord)
        : undefined;

const pickNumber = (
    sources: Array<MetaRecord | undefined>,
    keys: string[],
): number | undefined => {
    for (const source of sources) {
        if (!source) {
            continue;
        }

        for (const key of keys) {
            const candidate = source[key];
            if (typeof candidate === 'number') {
                return candidate;
            }
        }
    }

    return undefined;
};

const mapNotification = (
    payload: NotificationResponseItem,
): NotificationListItem => ({
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

const mapMeta = (
    meta?: NotificationIndexResponse['meta'],
): NotificationListMeta => {
    const root = toRecord(meta);
    const data = toRecord(root?.data);
    const envelope = toRecord(root?.envelope);
    const pagination =
        toRecord(envelope?.pagination) ?? toRecord(root?.pagination);

    return {
        total: pickNumber([root, data, pagination], ['total']),
        perPage: pickNumber([root, data, pagination], ['per_page', 'perPage']),
        currentPage: pickNumber(
            [root, data, pagination],
            ['current_page', 'currentPage'],
        ),
        lastPage: pickNumber(
            [root, data, pagination],
            ['last_page', 'lastPage'],
        ),
        unreadCount: pickNumber(
            [root, envelope],
            ['unread_count', 'unreadCount'],
        ),
    };
};

const resolveItems = (
    payload: NotificationIndexResponse | NotificationResponseItem[],
): NotificationResponseItem[] => {
    if (Array.isArray(payload)) {
        return payload;
    }

    if (Array.isArray(payload.items)) {
        return payload.items;
    }

    if (Array.isArray(payload.data)) {
        return payload.data;
    }

    return [];
};

export type UseNotificationsParams = NotificationListFilters;

export function useNotifications(
    params: UseNotificationsParams = {},
): UseQueryResult<NotificationListResult, ApiError> {
    const queryParams: Record<string, unknown> = { ...params };

    return useQuery<
        NotificationIndexResponse | NotificationResponseItem[],
        ApiError,
        NotificationListResult
    >({
        queryKey: queryKeys.notifications.list(queryParams),
        queryFn: async () => {
            const query = buildQuery(queryParams);
            const payload = await api.get<
                NotificationIndexResponse | NotificationResponseItem[]
            >(`/notifications${query}`);

            return payload as unknown as
                | NotificationIndexResponse
                | NotificationResponseItem[];
        },
        select: (response) => ({
            items: resolveItems(response).map(mapNotification),
            meta: mapMeta(Array.isArray(response) ? undefined : response.meta),
        }),
        placeholderData: keepPreviousData,
        staleTime: 15_000,
    });
}
