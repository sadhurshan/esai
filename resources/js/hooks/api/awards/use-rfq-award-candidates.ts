import {
    keepPreviousData,
    useQuery,
    type UseQueryResult,
} from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import type {
    ListRfqAwardCandidates200ResponseAllOfData,
    ListRfqAwardCandidates200ResponseAllOfDataMeta,
    ListRfqAwardCandidates200ResponseAllOfDataRfq,
    RfqAwardCandidateLine,
    RfqItemAwardSummary,
} from '@/sdk';
import { RFQsApi } from '@/sdk';

export type UseRfqAwardCandidatesResult = UseQueryResult<
    ListRfqAwardCandidates200ResponseAllOfData,
    unknown
> & {
    rfq?: ListRfqAwardCandidates200ResponseAllOfDataRfq;
    companyCurrency?: string;
    lines: RfqAwardCandidateLine[];
    awards: RfqItemAwardSummary[];
    meta?: ListRfqAwardCandidates200ResponseAllOfDataMeta;
};

export function useRfqAwardCandidates(
    rfqId: number,
): UseRfqAwardCandidatesResult {
    const rfqsApi = useSdkClient(RFQsApi);

    const query = useQuery<ListRfqAwardCandidates200ResponseAllOfData>({
        queryKey: queryKeys.awards.candidates(rfqId),
        enabled: Number.isFinite(rfqId) && rfqId > 0,
        placeholderData: keepPreviousData,
        queryFn: async () => {
            const response = await rfqsApi.listRfqAwardCandidates({
                rfqId: String(rfqId),
            });
            return response.data;
        },
        staleTime: 30_000,
    });

    return {
        ...query,
        rfq: query.data?.rfq,
        companyCurrency: query.data?.companyCurrency,
        lines: query.data?.lines ?? [],
        awards: query.data?.awards ?? [],
        meta: query.data?.meta,
    } satisfies UseRfqAwardCandidatesResult;
}
