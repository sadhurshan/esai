import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { RfqItem } from '@/types/sourcing';
import { fetchRfqDetail, mapRfqItem, type RFQDetailResponse } from './useRFQ';

export function useRfqItems(
    rfqId: number,
): UseQueryResult<RfqItem[], ApiError> {
    return useQuery<RFQDetailResponse, ApiError, RfqItem[]>({
        queryKey: queryKeys.rfqs.detail(rfqId),
        enabled: Number.isFinite(rfqId) && rfqId > 0,
        queryFn: () => fetchRfqDetail(rfqId),
        select: (response) => (response.items ?? []).map(mapRfqItem),
        staleTime: 30_000,
    });
}
