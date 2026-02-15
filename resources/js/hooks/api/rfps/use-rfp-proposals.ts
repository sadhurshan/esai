import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { RfpProposalSummaryResponse } from '@/types/rfp';
import { mapRfpProposalCollection } from './utils';

export interface UseRfpProposalsOptions {
    enabled?: boolean;
}

export function useRfpProposals(
    rfpId?: string | number,
    options: UseRfpProposalsOptions = {},
): UseQueryResult<RfpProposalSummaryResponse, ApiError> {
    const id = rfpId ? String(rfpId) : '';
    const enabled = options.enabled ?? id.length > 0;

    return useQuery<
        Record<string, unknown>,
        ApiError,
        RfpProposalSummaryResponse
    >({
        queryKey: queryKeys.rfps.proposals(id || 'undefined'),
        enabled,
        queryFn: async () => {
            const response = (await api.get<Record<string, unknown>>(
                `/rfps/${id}/proposals`,
            )) as unknown as Record<string, unknown>;
            return response;
        },
        select: (payload) => mapRfpProposalCollection(payload),
        staleTime: 30_000,
    });
}

export type UseRfpProposalsResult = ReturnType<typeof useRfpProposals>;
