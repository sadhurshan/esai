import {
    keepPreviousData,
    useQuery,
    type UseQueryResult,
} from '@tanstack/react-query';

import { api, buildQuery, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { AiChatThread, AiChatThreadResponse } from '@/types/ai-chat';

interface UseAiChatMessagesOptions {
    limit?: number;
    enabled?: boolean;
}

const DISABLED_THREAD_KEY = '__no_thread__';

const unwrap = <T>(payload: T | { data: T }): T => {
    if (
        payload &&
        typeof payload === 'object' &&
        'data' in (payload as Record<string, unknown>)
    ) {
        return (payload as { data: T }).data;
    }

    return payload as T;
};

export function useAiChatMessages(
    threadId: number | null,
    options: UseAiChatMessagesOptions = {},
): UseQueryResult<AiChatThread | undefined, ApiError> {
    const limit = options.limit ?? 50;
    const enabled = Boolean(threadId) && (options.enabled ?? true);
    const queryKeyParam = threadId ?? DISABLED_THREAD_KEY;

    return useQuery<AiChatThreadResponse, ApiError, AiChatThread | undefined>({
        queryKey: queryKeys.ai.chat.thread(queryKeyParam),
        enabled,
        queryFn: async () => {
            if (!threadId) {
                throw new Error('Thread id is required to load chat messages.');
            }

            const query = buildQuery({ limit });
            const response = await api.get<AiChatThreadResponse>(
                `/v1/ai/chat/threads/${threadId}${query}`,
            );
            return unwrap(response);
        },
        select: (response) => response.thread,
        placeholderData: keepPreviousData,
        staleTime: 5_000,
    });
}
