import { keepPreviousData, useMutation, useQuery, useQueryClient, type UseMutationResult, type UseQueryResult } from '@tanstack/react-query';

import { api, buildQuery, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { AiChatThread, AiChatThreadListResponse } from '@/types/ai-chat';

interface UseAiChatThreadsParams {
    status?: string | string[] | null;
    cursor?: string | null;
    per_page?: number;
}

interface NormalizedParams {
    keyPayload: Record<string, unknown>;
    queryPayload: Record<string, unknown>;
}

interface UseAiChatThreadsResult {
    items: AiChatThread[];
    meta?: Record<string, unknown>;
}

const STATUS_SENTINEL = '__all__';

const unwrap = <T>(payload: T | { data: T }): T => {
    if (payload && typeof payload === 'object' && 'data' in (payload as Record<string, unknown>)) {
        return (payload as { data: T }).data;
    }

    return payload as T;
};

const normalizeStatuses = (value?: string | string[] | null): string[] | undefined => {
    if (Array.isArray(value)) {
        return value
            .map((entry) => (typeof entry === 'string' ? entry.trim() : String(entry)))
            .filter((entry) => entry.length > 0)
            .sort();
    }

    if (typeof value === 'string') {
        const trimmed = value.trim();
        return trimmed === '' ? undefined : [trimmed];
    }

    return undefined;
};

const normalizeParams = (params: UseAiChatThreadsParams): NormalizedParams => {
    const normalizedStatuses = normalizeStatuses(params.status);

    const keyPayload: Record<string, unknown> = {
        status: normalizedStatuses ?? STATUS_SENTINEL,
        cursor: params.cursor ?? null,
        per_page: params.per_page ?? null,
    };

    const queryPayload: Record<string, unknown> = {
        status: normalizedStatuses,
        cursor: params.cursor ?? undefined,
        per_page: params.per_page ?? undefined,
    };

    return { keyPayload, queryPayload };
};

export function useAiChatThreads(
    params: UseAiChatThreadsParams = {},
): UseQueryResult<UseAiChatThreadsResult, ApiError> {
    const { keyPayload, queryPayload } = normalizeParams(params);

    return useQuery<AiChatThreadListResponse, ApiError, UseAiChatThreadsResult>({
        queryKey: queryKeys.ai.chat.threads(keyPayload),
        queryFn: async () => {
            const query = buildQuery(queryPayload);
            const response = await api.get<AiChatThreadListResponse>(`/v1/ai/chat/threads${query}`);
            return unwrap(response);
        },
        select: (response) => ({
            items: response.items ?? [],
            meta: response.meta,
        }),
        placeholderData: keepPreviousData,
        staleTime: 5_000,
    });
}

export interface CreateAiChatThreadPayload {
    title?: string | null;
}

export interface UseCreateAiChatThreadOptions {
    onSuccess?: (thread: AiChatThread) => void;
}

interface CreateThreadResponse {
    thread: AiChatThread;
}

export function useCreateAiChatThread(
    options: UseCreateAiChatThreadOptions = {},
): UseMutationResult<AiChatThread, ApiError, CreateAiChatThreadPayload | undefined> {
    const queryClient = useQueryClient();

    return useMutation<AiChatThread, ApiError, CreateAiChatThreadPayload | undefined>({
        mutationFn: async (payload) => {
            const response = await api.post<CreateThreadResponse>('/v1/ai/chat/threads', payload ?? {});
            const data = unwrap(response);
            return data.thread;
        },
        onSuccess: (thread) => {
            queryClient.invalidateQueries({ queryKey: queryKeys.ai.chat.root(), exact: false });

            if (options.onSuccess) {
                options.onSuccess(thread);
            }
        },
    });
}
