import { useMemo } from 'react';
import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { RFQsApi, type PageMeta, type RfqInvitation } from '@/sdk';

type RfqIdentifier = string | number | null | undefined;

interface RfqSuppliersPayload {
    items: RfqInvitation[];
    meta?: PageMeta;
}

async function fetchRfqSuppliers(rfqsApi: RFQsApi, rfqId: string | number): Promise<RfqSuppliersPayload> {
    const response = await rfqsApi.listRfqInvitations({
        rfqId: String(rfqId),
    });

    return {
        items: response.data.items ?? [],
        meta: response.data.meta,
    } satisfies RfqSuppliersPayload;
}

export type UseRfqSuppliersResult = UseQueryResult<RfqSuppliersPayload> & {
    items: RfqInvitation[];
    meta?: PageMeta;
};

export function useRfqSuppliers(rfqId: RfqIdentifier): UseRfqSuppliersResult {
    const rfqsApi = useSdkClient(RFQsApi);
    const enabled = Boolean(rfqId);

    const query = useQuery<RfqSuppliersPayload>({
        queryKey: queryKeys.rfqs.suppliers(rfqId ?? 'undefined'),
        enabled,
        queryFn: () => fetchRfqSuppliers(rfqsApi, rfqId as string | number),
    });

    const data = useMemo<RfqSuppliersPayload>(() => {
        return query.data ?? { items: [], meta: undefined };
    }, [query.data]);
    const items = data.items;

    return {
        ...query,
        data,
        items,
        meta: data.meta,
    } as UseRfqSuppliersResult;
}
