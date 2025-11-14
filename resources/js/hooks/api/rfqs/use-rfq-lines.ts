import { useMemo } from 'react';
import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { RFQsApi, type RequestMeta, type RfqItem } from '@/sdk';

type RfqIdentifier = string | number | null | undefined;

export interface UseRfqLinesParams {
    rfqId: RfqIdentifier;
    page?: number;
    perPage?: number;
    sort?: string;
}

type RfqLinesPayload = {
    items: RfqItem[];
    meta?: RequestMeta;
};

type FetchRfqLinesParams = {
    rfqsApi: RFQsApi;
    rfqId: string | number;
    page?: number;
    perPage?: number;
    sort?: string;
};

async function fetchRfqLines({
    rfqsApi,
    rfqId,
}: FetchRfqLinesParams): Promise<RfqLinesPayload> {
    // TODO: pass pagination/sort params once listRfqLines supports them per deep spec.
    const response = await rfqsApi.listRfqLines({
        rfqId: String(rfqId),
    });

    return {
        items: response.data.items ?? [],
        meta: response.meta,
    };
}

export type UseRfqLinesResult = UseQueryResult<RfqLinesPayload> & {
    items: RfqItem[];
    meta?: RequestMeta;
};

export function useRfqLines(params: UseRfqLinesParams): UseRfqLinesResult {
    const { rfqId, page, perPage, sort } = params;
    const rfqsApi = useSdkClient(RFQsApi);
    const enabled = rfqId !== null && rfqId !== undefined;

    const queryKey = [
        ...queryKeys.rfqs.lines(rfqId ?? 'undefined'),
        { page, perPage, sort },
    ] as const;

    const query = useQuery<RfqLinesPayload>({
        queryKey,
        enabled,
        queryFn: () =>
            fetchRfqLines({
                rfqsApi,
                rfqId: rfqId as string | number,
                page,
                perPage,
                sort,
            }),
    });

    const payload = useMemo<RfqLinesPayload>(
        () => query.data ?? { items: [], meta: undefined },
        [query.data],
    );

    return {
        ...query,
        data: payload,
        items: payload.items,
        meta: payload.meta,
    } as UseRfqLinesResult;
}
