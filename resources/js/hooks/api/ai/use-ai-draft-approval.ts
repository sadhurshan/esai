import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { AiActionDraftResponse } from '@/types/ai-chat';

interface ApproveDraftVariables {
    draftId: number;
}

interface RejectDraftVariables {
    draftId: number;
    reason: string;
}

const unwrap = <T>(payload: T | { data: T }): T => {
    if (payload && typeof payload === 'object' && 'data' in (payload as Record<string, unknown>)) {
        return (payload as { data: T }).data;
    }

    return payload as T;
};

const invalidateChatQueries = (queryClient: ReturnType<typeof useQueryClient>, threadId: number): void => {
    queryClient.invalidateQueries({ queryKey: queryKeys.ai.chat.thread(threadId), exact: false });
    queryClient.invalidateQueries({ queryKey: queryKeys.ai.chat.root(), exact: false });
};

export function useAiDraftApprove(
    threadId: number | null,
): UseMutationResult<AiActionDraftResponse, ApiError, ApproveDraftVariables> {
    const queryClient = useQueryClient();

    return useMutation<AiActionDraftResponse, ApiError, ApproveDraftVariables>({
        mutationFn: async (variables) => {
            if (!threadId) {
                throw new Error('Thread is not ready for approvals.');
            }

            const response = await api.post<AiActionDraftResponse>(`/v1/ai/drafts/${variables.draftId}/approve`, {
                thread_id: threadId,
            });

            return unwrap(response);
        },
        onSuccess: (_data, _variables) => {
            if (!threadId) {
                return;
            }

            invalidateChatQueries(queryClient, threadId);
        },
    });
}

export function useAiDraftReject(
    threadId: number | null,
): UseMutationResult<AiActionDraftResponse, ApiError, RejectDraftVariables> {
    const queryClient = useQueryClient();

    return useMutation<AiActionDraftResponse, ApiError, RejectDraftVariables>({
        mutationFn: async (variables) => {
            if (!threadId) {
                throw new Error('Thread is not ready for rejections.');
            }

            const response = await api.post<AiActionDraftResponse>(`/v1/ai/drafts/${variables.draftId}/reject`, {
                thread_id: threadId,
                reason: variables.reason,
            });

            return unwrap(response);
        },
        onSuccess: (_data, _variables) => {
            if (!threadId) {
                return;
            }

            invalidateChatQueries(queryClient, threadId);
        },
    });
}
