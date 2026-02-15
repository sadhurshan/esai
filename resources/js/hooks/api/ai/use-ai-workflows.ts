import {
    keepPreviousData,
    useQuery,
    type UseQueryResult,
} from '@tanstack/react-query';

import { api, buildQuery, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type {
    AiWorkflowListResponse,
    AiWorkflowSummary,
} from '@/types/ai-workflows';

export interface UseAiWorkflowsParams extends Record<string, unknown> {
    status?: string | string[];
    workflow_type?: string | null;
    cursor?: string | null;
    per_page?: number;
}

interface UseAiWorkflowsResult {
    items: AiWorkflowSummary[];
    meta?: AiWorkflowListResponse['meta'];
}

const DISABLED_STATUS = '__none__';

type NormalizedParams = {
    keyPayload: Record<string, unknown>;
    queryPayload: Record<string, unknown>;
};

const normalizeParams = (params: UseAiWorkflowsParams): NormalizedParams => {
    const normalizeStatus = (raw?: string | string[]) => {
        if (Array.isArray(raw)) {
            return raw
                .map((value) =>
                    typeof value === 'string' ? value.trim() : String(value),
                )
                .filter((value) => value.length > 0)
                .sort();
        }

        if (typeof raw === 'string') {
            const trimmed = raw.trim();
            return trimmed === '' ? undefined : trimmed;
        }

        return undefined;
    };

    const normalizedStatus = normalizeStatus(params.status);

    const keyPayload: Record<string, unknown> = {
        status: normalizedStatus ?? DISABLED_STATUS,
        workflow_type: params.workflow_type ?? null,
        cursor: params.cursor ?? null,
        per_page: params.per_page ?? null,
    };

    const queryPayload: Record<string, unknown> = {
        status: normalizedStatus,
        workflow_type: params.workflow_type ?? undefined,
        cursor: params.cursor ?? undefined,
        per_page: params.per_page ?? undefined,
    };

    return { keyPayload, queryPayload };
};

export function useAiWorkflows(
    params: UseAiWorkflowsParams = {},
): UseQueryResult<UseAiWorkflowsResult, ApiError> {
    const { keyPayload, queryPayload } = normalizeParams(params);

    return useQuery<AiWorkflowListResponse, ApiError, UseAiWorkflowsResult>({
        queryKey: queryKeys.ai.workflows.list(keyPayload),
        queryFn: async () => {
            const query = buildQuery(queryPayload);
            const response = await api.get<AiWorkflowListResponse>(
                `/v1/ai/workflows${query}`,
            );
            return response.data;
        },
        select: (response) => ({
            items: response.items,
            meta: response.meta,
        }),
        placeholderData: keepPreviousData,
        staleTime: 10_000,
    });
}
