import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { RfpDetail } from '@/types/rfp';
import { mapRfpDetail } from './utils';

export interface UseRfpOptions {
    enabled?: boolean;
}

export function useRfp(rfpId?: string | number, options: UseRfpOptions = {}): UseQueryResult<RfpDetail, ApiError> {
    const id = rfpId ? String(rfpId) : '';
    const enabled = options.enabled ?? id.length > 0;

    return useQuery<Record<string, unknown>, ApiError, RfpDetail>({
        queryKey: queryKeys.rfps.detail(id || 'undefined'),
        enabled,
        queryFn: async () => {
            const response = (await api.get<Record<string, unknown>>(`/rfps/${id}`)) as unknown as Record<string, unknown>;
            return response;
        },
        select: (payload) => mapRfpDetail(payload),
        staleTime: 30_000,
    });
}

export type UseRfpResult = ReturnType<typeof useRfp>;
