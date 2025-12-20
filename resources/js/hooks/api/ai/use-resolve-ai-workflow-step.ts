import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { AiWorkflowStepResponse } from '@/types/ai-workflows';

export interface ResolveAiWorkflowStepInput {
    workflowId: string;
    step_index: number;
    approval: boolean;
    output?: Record<string, unknown>;
    notes?: string | null;
}

export type ResolveAiWorkflowStepResult = AiWorkflowStepResponse;

export function useResolveAiWorkflowStep(): UseMutationResult<
    ResolveAiWorkflowStepResult,
    ApiError,
    ResolveAiWorkflowStepInput
> {
    const queryClient = useQueryClient();

    return useMutation<ResolveAiWorkflowStepResult, ApiError, ResolveAiWorkflowStepInput>({
        mutationFn: async ({ workflowId, ...payload }) => {
            const response = await api.post<ResolveAiWorkflowStepResult>(
                `/v1/ai/workflows/${workflowId}/complete`,
                payload,
            );
            return response.data;
        },
        onSuccess: (response, variables) => {
            queryClient.invalidateQueries({ queryKey: ['ai', 'workflows', 'list'] });

            if (variables.workflowId) {
                queryClient.setQueryData(queryKeys.ai.workflows.step(variables.workflowId), {
                    workflow: response.workflow,
                    step: response.next_step ?? response.step,
                    next_step: response.next_step,
                });
            }
        },
    });
}
