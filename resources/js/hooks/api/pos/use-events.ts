import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { PurchaseOrderEvent } from '@/types/sourcing';

interface PurchaseOrderEventsEnvelope {
    items?: PurchaseOrderEvent[];
    data?: PurchaseOrderEvent[];
    events?: PurchaseOrderEvent[];
}

function extractEvents(payload: PurchaseOrderEventsEnvelope | PurchaseOrderEvent[]): PurchaseOrderEvent[] {
    if (Array.isArray(payload)) {
        return payload;
    }

    if (Array.isArray(payload.items)) {
        return payload.items;
    }

    if (Array.isArray(payload.data)) {
        return payload.data;
    }

    if (Array.isArray(payload.events)) {
        return payload.events;
    }

    return [];
}

export function usePoEvents(poId: number): UseQueryResult<PurchaseOrderEvent[], unknown> {
    return useQuery<PurchaseOrderEvent[]>({
        queryKey: queryKeys.purchaseOrders.events(poId),
        enabled: Number.isFinite(poId) && poId > 0,
        queryFn: async () => {
            const response = (await api.get<PurchaseOrderEventsEnvelope | PurchaseOrderEvent[]>(
                `/purchase-orders/${poId}/events`,
            )) as unknown as PurchaseOrderEventsEnvelope | PurchaseOrderEvent[];

            return extractEvents(response);
        },
        staleTime: 15_000,
    });
}

// TODO: replace manual axios call with the SDK once the OpenAPI spec exposes the events endpoint.