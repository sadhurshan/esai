import {
    useMutation,
    useQuery,
    useQueryClient,
    type UseQueryResult,
} from '@tanstack/react-query';
import { useCallback, useMemo } from 'react';

import {
    useApiClientContext,
    useSdkClient,
} from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import {
    HttpError,
    RFQsApi,
    type ApiSuccessResponse,
    type RfqClarification,
} from '@/sdk';

type RfqIdentifier = string | number | null | undefined;

export interface UseRfqClarificationsOptions {
    enabled?: boolean;
}

async function fetchClarifications(
    rfqsApi: RFQsApi,
    rfqId: string | number,
): Promise<RfqClarification[]> {
    const response = await rfqsApi.listRfqClarifications({
        rfqId: String(rfqId),
    });

    return response.data.items ?? [];
}

export interface ClarificationSubmissionPayload {
    message: string;
    attachments?: File[];
}

export type UseRfqClarificationsResult = UseQueryResult<RfqClarification[]> & {
    items: RfqClarification[];
    askQuestion: (
        payload: ClarificationSubmissionPayload,
    ) => Promise<ApiSuccessResponse>;
    answerQuestion: (
        payload: ClarificationSubmissionPayload,
    ) => Promise<ApiSuccessResponse>;
    isSubmittingQuestion: boolean;
    isSubmittingAnswer: boolean;
};

export function useRfqClarifications(
    rfqId: RfqIdentifier,
    options: UseRfqClarificationsOptions = {},
): UseRfqClarificationsResult {
    const rfqsApi = useSdkClient(RFQsApi);
    const { configuration } = useApiClientContext();
    const queryClient = useQueryClient();
    const enabled = options.enabled ?? Boolean(rfqId);

    const query = useQuery<RfqClarification[]>({
        queryKey: queryKeys.rfqs.clarifications(rfqId ?? 'undefined'),
        enabled,
        queryFn: () => fetchClarifications(rfqsApi, rfqId as string | number),
    });

    const submitClarification = useCallback(
        async (
            path: 'clarifications/question' | 'clarifications/answer',
            payload: ClarificationSubmissionPayload,
        ) => {
            if (!rfqId || !enabled) {
                throw new Error(
                    'RFQ identifier is required to submit a clarification.',
                );
            }

            const message = payload.message?.trim();

            if (!message) {
                throw new Error('Clarification message is required.');
            }

            const formData = new FormData();
            formData.append('message', message);

            (payload.attachments ?? []).forEach((file) => {
                formData.append('attachments[]', file);
            });

            const fetchImpl = configuration.fetchApi ?? fetch;
            const url = `${configuration.basePath}/api/rfqs/${String(rfqId)}/${path}`;

            const response = await fetchImpl(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            if (!response.ok) {
                const errorBody = await response
                    .clone()
                    .json()
                    .catch(() => undefined);
                throw new HttpError(response, errorBody);
            }

            return (await response.json()) as ApiSuccessResponse;
        },
        [configuration, enabled, rfqId],
    );

    const questionMutation = useMutation({
        mutationFn: async (payload: ClarificationSubmissionPayload) =>
            submitClarification('clarifications/question', payload),
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: queryKeys.rfqs.clarifications(rfqId ?? 'undefined'),
            });
        },
    });

    const answerMutation = useMutation({
        mutationFn: async (payload: ClarificationSubmissionPayload) =>
            submitClarification('clarifications/answer', payload),
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: queryKeys.rfqs.clarifications(rfqId ?? 'undefined'),
            });
        },
    });

    const items = useMemo(() => {
        if (!enabled) {
            return [];
        }

        return query.data ?? [];
    }, [enabled, query.data]);

    return {
        ...query,
        items,
        askQuestion: async (payload: ClarificationSubmissionPayload) =>
            questionMutation.mutateAsync(payload),
        answerQuestion: async (payload: ClarificationSubmissionPayload) =>
            answerMutation.mutateAsync(payload),
        isSubmittingQuestion: enabled ? questionMutation.isPending : false,
        isSubmittingAnswer: enabled ? answerMutation.isPending : false,
    };
}
