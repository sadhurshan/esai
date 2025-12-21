import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';

export interface StartAiWorkflowVariables {
    workflow_type: string;
    rfq_id?: string | null;
    goal?: string | null;
    inputs?: Record<string, unknown>;
}

export interface StartAiWorkflowResult {
    workflow_id: string;
}

export function useStartAiWorkflow(
    threadId?: number | null,
): UseMutationResult<StartAiWorkflowResult, ApiError, StartAiWorkflowVariables> {
    const queryClient = useQueryClient();

    return useMutation<StartAiWorkflowResult, ApiError, StartAiWorkflowVariables>({
        mutationFn: async (variables) => {
            const response = await api.post<StartAiWorkflowResult>('/v1/ai/workflows/start', {
                ...variables,
                thread_id: threadId ?? undefined,
            });

            return response.data;
        },
        onSuccess: (_data) => {
            if (threadId) {
                queryClient.invalidateQueries({ queryKey: queryKeys.ai.chat.thread(threadId), exact: false });
                queryClient.invalidateQueries({ queryKey: queryKeys.ai.chat.root(), exact: false });
            }

            queryClient.invalidateQueries({ queryKey: ['ai', 'workflows'], exact: false });
        },
    });
}
