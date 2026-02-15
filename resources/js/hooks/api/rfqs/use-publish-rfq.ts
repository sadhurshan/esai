import { useMutation, useQueryClient } from '@tanstack/react-query';

import { useApiClientContext } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { type CreateRfq201Response, CreateRfq201ResponseFromJSON } from '@/sdk';

export interface PublishRfqPayload {
    rfqId: string | number;
    dueAt: Date | string;
    publishAt?: Date | string | null;
    notifySuppliers?: boolean;
    message?: string;
}

export function usePublishRfq() {
    const { configuration } = useApiClientContext();
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (
            payload: PublishRfqPayload,
        ): Promise<CreateRfq201Response> => {
            const body = serializePublishPayload(payload);

            const fetchApi = configuration.fetchApi ?? fetch;
            const url = `${configuration.basePath.replace(/\/$/, '')}/api/rfqs/${payload.rfqId}/publish`;
            const response = await fetchApi(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(body),
            });
            const data = await response.json();
            return CreateRfq201ResponseFromJSON(data);
        },
        onSuccess: (_response, variables) => {
            const rfqId = String(variables.rfqId);
            void queryClient.invalidateQueries({
                queryKey: queryKeys.rfqs.detail(rfqId),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.rfqs.timeline(rfqId),
            });
        },
    });
}

function serializePublishPayload(
    payload: PublishRfqPayload,
): Record<string, unknown> {
    if (!payload.dueAt) {
        throw new Error('Due date is required to publish this RFQ.');
    }

    const body: Record<string, unknown> = {
        due_at: normalizeDateInput(payload.dueAt),
    };

    if (payload.publishAt !== undefined) {
        body.publish_at =
            payload.publishAt === null
                ? null
                : normalizeDateInput(payload.publishAt);
    }

    if (payload.notifySuppliers !== undefined) {
        body.notify_suppliers = payload.notifySuppliers;
    }

    if (payload.message !== undefined) {
        body.message = payload.message;
    }

    return body;
}

function normalizeDateInput(value: Date | string): string {
    if (value instanceof Date) {
        return value.toISOString();
    }

    return value;
}
