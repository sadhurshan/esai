import { useMutation, useQueryClient } from '@tanstack/react-query';

import { api, ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { Rfq } from '@/sdk';

type RfqIdentifier = string | number;

export interface RfqDeadlineExtensionSummary {
    id: number | string;
    rfq_id: number | string;
    previous_due_at?: string | null;
    new_due_at?: string | null;
    reason: string;
    extended_by?: number | string;
    extended_by_user?: {
        id: number | string;
        name?: string | null;
        role?: string | null;
    } | null;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface ExtendDeadlinePayload {
    rfqId: RfqIdentifier;
    newDueAt: Date;
    reason: string;
    notifySuppliers: boolean;
}

interface ExtendDeadlineResponse {
    extension: RfqDeadlineExtensionSummary;
    rfq: Rfq;
}

export function useExtendRfqDeadline() {
    const queryClient = useQueryClient();

    return useMutation<ExtendDeadlineResponse, ApiError, ExtendDeadlinePayload>(
        {
            mutationFn: async ({
                rfqId,
                newDueAt,
                reason,
                notifySuppliers,
            }) => {
                const response = await api.post<ExtendDeadlineResponse>(
                    `/rfqs/${rfqId}/extend-deadline`,
                    {
                        new_due_at: newDueAt.toISOString(),
                        reason,
                        notify_suppliers: notifySuppliers,
                    },
                );

                return response.data;
            },
            onSuccess: (_data, variables) => {
                const { rfqId } = variables;
                queryClient.invalidateQueries({
                    queryKey: queryKeys.rfqs.detail(rfqId),
                });
                queryClient.invalidateQueries({
                    queryKey: queryKeys.rfqs.timeline(rfqId),
                });
            },
        },
    );
}
