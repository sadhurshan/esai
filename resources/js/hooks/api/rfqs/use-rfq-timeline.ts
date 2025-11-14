import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { RFQsApi, type RfqTimelineEntry } from '@/sdk';

type RfqIdentifier = string | number | null | undefined;

type FetchTimelineParams = {
    rfqsApi: RFQsApi;
    rfqId: string | number;
};

async function fetchRfqTimeline({ rfqsApi, rfqId }: FetchTimelineParams): Promise<RfqTimelineEntry[]> {
    const response = await rfqsApi.listRfqTimeline({
        rfqId: String(rfqId),
    });

    return response.data.items ?? [];
}

export type UseRfqTimelineResult = UseQueryResult<RfqTimelineEntry[]> & {
    items: RfqTimelineEntry[];
};

export function useRfqTimeline(rfqId: RfqIdentifier): UseRfqTimelineResult {
    const rfqsApi = useSdkClient(RFQsApi);
    const enabled = rfqId !== null && rfqId !== undefined;

    const query = useQuery<RfqTimelineEntry[]>({
        queryKey: queryKeys.rfqs.timeline(rfqId ?? 'undefined'),
        enabled,
        queryFn: () => fetchRfqTimeline({ rfqsApi, rfqId: rfqId as string | number }),
        initialData: [],
    });

    return {
        ...query,
        items: query.data ?? [],
    } as UseRfqTimelineResult;
}
