import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { HttpError, ReceivingApi } from '@/sdk';
import type { GoodsReceiptNoteDetail } from '@/types/sourcing';

import { mapGrnDetail } from './utils';

export function useGrn(
    grnId: number,
): UseQueryResult<GoodsReceiptNoteDetail, HttpError> {
    const receivingApi = useSdkClient(ReceivingApi);

    return useQuery<Record<string, unknown>, HttpError, GoodsReceiptNoteDetail>(
        {
            queryKey: queryKeys.receiving.detail(grnId),
            enabled: Number.isFinite(grnId) && grnId > 0,
            queryFn: async () =>
                (await receivingApi.showGrn(grnId)) as Record<string, unknown>,
            select: (response) => mapGrnDetail(response),
            staleTime: 15_000,
        },
    );
}
