import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { AiWorkflowStepResponse } from '@/types/ai-workflows';

interface UseAiWorkflowStepOptions {
    enabled?: boolean;
}

const NO_WORKFLOW_SELECTED = '__ai__workflow__unselected__';

export function useAiWorkflowStep(
    workflowId?: string | null,
    options: UseAiWorkflowStepOptions = {},
): UseQueryResult<AiWorkflowStepResponse, ApiError> {
    const isEnabled = Boolean(workflowId) && (options.enabled ?? true);
    const queryKey = queryKeys.ai.workflows.step(
        workflowId ?? NO_WORKFLOW_SELECTED,
    );

    return useQuery<AiWorkflowStepResponse, ApiError>({
        queryKey,
        enabled: isEnabled,
        queryFn: async () => {
            if (!workflowId) {
                throw new Error(
                    'Workflow ID is required to fetch step details.',
                );
            }

            const response = await api.get<AiWorkflowStepResponse>(
                `/v1/ai/workflows/${workflowId}/next`,
            );
            return response.data;
        },
        refetchOnReconnect: true,
        refetchOnMount: isEnabled,
        refetchOnWindowFocus: false,
    });
}
