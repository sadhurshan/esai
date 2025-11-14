import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { RFQsApi, type Rfq } from '@/sdk';

type RfqIdentifier = string | number | null | undefined;

export interface UseRfqOptions {
    enabled?: boolean;
}

async function fetchRfq(rfqsApi: RFQsApi, rfqId: string | number): Promise<Rfq> {
    const response = await rfqsApi.showRfq({
        rfqId: String(rfqId),
    });

    return response.data;
}

export function useRfq(rfqId: RfqIdentifier, options: UseRfqOptions = {}): UseQueryResult<Rfq> {
    const rfqsApi = useSdkClient(RFQsApi);
    const enabled = options.enabled ?? Boolean(rfqId);

    return useQuery<Rfq>({
        queryKey: queryKeys.rfqs.detail(rfqId ?? 'undefined'),
        enabled,
        queryFn: () => fetchRfq(rfqsApi, rfqId as string | number),
    });
}

export type UseRfqResult = ReturnType<typeof useRfq>;
