import {
    keepPreviousData,
    useQuery,
    type UseQueryResult,
} from '@tanstack/react-query';

import { api, buildQuery, type ApiError } from '@/lib/api';
import { toCursorMeta, type CursorPaginationMeta } from '@/lib/pagination';
import { queryKeys } from '@/lib/queryKeys';
import type {
    EventDeliveryFilters,
    EventDeliveryItem,
    EventDeliveryStatus,
} from '@/types/notifications';

interface EventDeliveryResponseItem {
    id: number;
    subscription_id: number;
    endpoint?: string | null;
    event: string;
    status: EventDeliveryStatus;
    attempts: number;
    max_attempts?: number | null;
    latency_ms?: number | null;
    response_code?: number | null;
    response_body?: string | null;
    last_error?: string | null;
    payload?: Record<string, unknown> | null;
    dead_lettered_at?: string | null;
    dispatched_at?: string | null;
    delivered_at?: string | null;
    created_at?: string | null;
}

interface EventDeliveryIndexResponse {
    items: EventDeliveryResponseItem[];
    meta?: Record<string, unknown> | null;
}

const mapDelivery = (
    payload: EventDeliveryResponseItem,
): EventDeliveryItem => ({
    id: payload.id,
    subscriptionId: payload.subscription_id,
    endpoint: payload.endpoint,
    event: payload.event,
    status: payload.status,
    attempts: payload.attempts,
    maxAttempts: payload.max_attempts,
    latencyMs: payload.latency_ms,
    responseCode: payload.response_code,
    responseBody: payload.response_body,
    lastError: payload.last_error,
    payload: payload.payload ?? null,
    deadLetteredAt: payload.dead_lettered_at,
    dispatchedAt: payload.dispatched_at,
    deliveredAt: payload.delivered_at,
    createdAt: payload.created_at,
});

export interface UseDeliveriesResult {
    items: EventDeliveryItem[];
    meta?: CursorPaginationMeta;
}

export type UseDeliveriesParams = EventDeliveryFilters;

export function useDeliveries(
    params: UseDeliveriesParams = {},
): UseQueryResult<UseDeliveriesResult, ApiError> {
    const queryParams: Record<string, unknown> = { ...params };

    return useQuery<EventDeliveryIndexResponse, ApiError, UseDeliveriesResult>({
        queryKey: queryKeys.events.deliveries(queryParams),
        queryFn: async () => {
            const query = buildQuery(queryParams);
            return (await api.get<EventDeliveryIndexResponse>(
                `/events/deliveries${query}`,
            )) as unknown as EventDeliveryIndexResponse;
        },
        select: (response) => ({
            items: response.items.map(mapDelivery),
            meta: toCursorMeta(response.meta ?? undefined),
        }),
        placeholderData: keepPreviousData,
        staleTime: 15_000,
    });
}
