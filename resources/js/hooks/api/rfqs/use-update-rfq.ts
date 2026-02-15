import { useMutation, useQueryClient } from '@tanstack/react-query';

import type { RfqMethod } from '@/constants/rfq';
import { useApiClientContext } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { type CreateRfq201Response, CreateRfq201ResponseFromJSON } from '@/sdk';

export interface UseUpdateRfqOptions {
    onSuccess?: (response: CreateRfq201Response) => void;
}

export interface UpdateRfqPayload {
    rfqId: string | number;
    title?: string;
    method?: RfqMethod;
    dueAt?: Date | string | null;
    openBidding?: boolean;
    notes?: string;
}

export function useUpdateRfq(options: UseUpdateRfqOptions = {}) {
    const { configuration } = useApiClientContext();
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: UpdateRfqPayload) => {
            const body = serializeUpdatePayload(payload);
            if (Object.keys(body).length === 0) {
                throw new Error('No changes were provided for this RFQ.');
            }

            const fetchApi = configuration.fetchApi ?? fetch;
            const url = `${configuration.basePath.replace(/\/$/, '')}/api/rfqs/${payload.rfqId}`;
            const response = await fetchApi(url, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(body),
            });
            const data = await response.json();
            return CreateRfq201ResponseFromJSON(data);
        },
        onSuccess: (response: CreateRfq201Response) => {
            const rfqId = response.data.id;
            queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.root() });
            queryClient.invalidateQueries({
                queryKey: queryKeys.rfqs.detail(rfqId),
            });
            if (options.onSuccess) {
                options.onSuccess(response);
            }
        },
    });
}

function serializeUpdatePayload(
    payload: UpdateRfqPayload,
): Record<string, unknown> {
    const body: Record<string, unknown> = {};

    if (payload.title !== undefined) {
        body.title = payload.title;
    }

    if (payload.method !== undefined) {
        body.method = payload.method;
    }

    if (payload.openBidding !== undefined) {
        body.open_bidding = payload.openBidding;
    }

    if (payload.notes !== undefined) {
        body.notes = payload.notes;
    }

    if (payload.dueAt !== undefined) {
        if (payload.dueAt === null) {
            body.due_at = null;
        } else {
            const value =
                payload.dueAt instanceof Date
                    ? payload.dueAt.toISOString()
                    : payload.dueAt;
            body.due_at = value;
            body.close_at = value;
        }
    }

    return body;
}
