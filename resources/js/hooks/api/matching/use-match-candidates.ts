import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import type { MatchCandidate } from '@/types/sourcing';
import { HttpError, MatchingApi, type ListMatchCandidatesQuery } from '@/sdk';

import { mapMatchCandidate } from './utils';

interface MatchCandidateCollectionResponse {
    items?: Record<string, unknown>[];
    data?: Record<string, unknown>[];
    meta?: Record<string, unknown> | null;
}

export interface MatchCandidateListResult {
    items: MatchCandidate[];
    meta?: Record<string, unknown> | null;
}

const DEFAULT_PER_PAGE = 25;

export type UseMatchCandidatesParams = ListMatchCandidatesQuery;

export const useMatchCandidates = (
    params: UseMatchCandidatesParams = {},
): UseQueryResult<MatchCandidateListResult, HttpError> => {
    const matchingApi = useSdkClient(MatchingApi);

    return useQuery<MatchCandidateCollectionResponse, HttpError, MatchCandidateListResult>({
        queryKey: queryKeys.matching.candidates({
            cursor: params.cursor,
            perPage: params.perPage ?? DEFAULT_PER_PAGE,
            status: params.status,
            supplierId: params.supplierId,
            dateFrom: params.dateFrom,
            dateTo: params.dateTo,
            search: params.search,
        }),
        enabled: true,
        placeholderData: keepPreviousData,
        queryFn: async () =>
            (await matchingApi.listCandidates({
                cursor: params.cursor,
                perPage: params.perPage ?? DEFAULT_PER_PAGE,
                status: params.status,
                supplierId: params.supplierId,
                dateFrom: params.dateFrom,
                dateTo: params.dateTo,
                search: params.search,
            })) as MatchCandidateCollectionResponse,
        select: (response) => {
            const rawItems = (response?.items ?? response?.data ?? []) as unknown[];
            const items = rawItems
                .filter((item): item is Record<string, unknown> => typeof item === 'object' && item !== null)
                .map((item) => mapMatchCandidate(item));

            return {
                items,
                meta: response?.meta ?? null,
            };
        },
        staleTime: 15_000,
        gcTime: 60_000,
    });
};
