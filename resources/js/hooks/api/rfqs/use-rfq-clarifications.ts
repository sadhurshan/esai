import { useMemo } from 'react';
import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { RFQsApi, type ApiSuccessResponse, type RfqClarification } from '@/sdk';

type RfqIdentifier = string | number | null | undefined;

async function fetchClarifications(rfqsApi: RFQsApi, rfqId: string | number): Promise<RfqClarification[]> {
    const response = await rfqsApi.listRfqClarifications({
        rfqId: String(rfqId),
    });

    return response.data.items ?? [];
}

interface ClarificationPayload {
    body: string;
}

export type UseRfqClarificationsResult = UseQueryResult<RfqClarification[]> & {
    items: RfqClarification[];
    askQuestion: (payload: ClarificationPayload) => Promise<ApiSuccessResponse>;
    answerQuestion: (payload: ClarificationPayload) => Promise<ApiSuccessResponse>;
    isSubmittingQuestion: boolean;
    isSubmittingAnswer: boolean;
};

export function useRfqClarifications(rfqId: RfqIdentifier): UseRfqClarificationsResult {
    const rfqsApi = useSdkClient(RFQsApi);
    const queryClient = useQueryClient();
    const enabled = Boolean(rfqId);

    const query = useQuery<RfqClarification[]>({
        queryKey: queryKeys.rfqs.clarifications(rfqId ?? 'undefined'),
        enabled,
        queryFn: () => fetchClarifications(rfqsApi, rfqId as string | number),
    });

    const questionMutation = useMutation({
        mutationFn: async (payload: ClarificationPayload) => {
            if (!rfqId) {
                throw new Error('RFQ identifier is required to submit a clarification question.');
            }

            return rfqsApi.createRfqClarificationQuestion({
                rfqId: String(rfqId),
                createRfqAmendmentRequest: {
                    body: payload.body,
                },
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.clarifications(rfqId ?? 'undefined') });
        },
    });

    const answerMutation = useMutation({
        mutationFn: async (payload: ClarificationPayload) => {
            if (!rfqId) {
                throw new Error('RFQ identifier is required to submit a clarification answer.');
            }

            return rfqsApi.createRfqClarificationAnswer({
                rfqId: String(rfqId),
                createRfqAmendmentRequest: {
                    body: payload.body,
                },
            });
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.clarifications(rfqId ?? 'undefined') });
        },
    });

    const items = useMemo(() => query.data ?? [], [query.data]);

    return {
        ...query,
        items,
        askQuestion: async (payload: ClarificationPayload) => questionMutation.mutateAsync(payload),
        answerQuestion: async (payload: ClarificationPayload) => answerMutation.mutateAsync(payload),
        isSubmittingQuestion: questionMutation.isPending,
        isSubmittingAnswer: answerMutation.isPending,
    } as UseRfqClarificationsResult;
}
