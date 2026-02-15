import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { useMemo } from 'react';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { RFQsApi, type RfqAttachment } from '@/sdk';

type RfqIdentifier = string | number | null | undefined;

async function fetchRfqAttachments(
    rfqsApi: RFQsApi,
    rfqId: string | number,
): Promise<RfqAttachment[]> {
    const response = await rfqsApi.listRfqAttachments({
        rfqId: String(rfqId),
    });

    return response.data.items ?? [];
}

export type UseRfqAttachmentsResult = UseQueryResult<RfqAttachment[]> & {
    items: RfqAttachment[];
};

export function useRfqAttachments(
    rfqId: RfqIdentifier,
): UseRfqAttachmentsResult {
    const rfqsApi = useSdkClient(RFQsApi);
    const enabled = rfqId !== null && rfqId !== undefined;

    const query = useQuery<RfqAttachment[]>({
        queryKey: queryKeys.rfqs.attachments(rfqId ?? 'undefined'),
        enabled,
        queryFn: () => fetchRfqAttachments(rfqsApi, rfqId as string | number),
    });

    const items = useMemo(() => query.data ?? [], [query.data]);

    return {
        ...query,
        data: items,
        items,
    } as UseRfqAttachmentsResult;
}
