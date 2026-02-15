import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { useMemo } from 'react';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { MoneyApi, type CursorMeta, type TaxCode } from '@/sdk';

export interface UseTaxCodesParams extends Record<string, unknown> {
    cursor?: string;
    search?: string;
    active?: boolean;
    type?: string;
}

interface TaxCodeListPayload {
    items: TaxCode[];
    meta?: CursorMeta;
}

export type UseTaxCodesResult = UseQueryResult<TaxCodeListPayload> & {
    items: TaxCode[];
    meta?: CursorMeta;
};

interface UseTaxCodesOptions {
    enabled?: boolean;
}

export function useTaxCodes(
    params?: UseTaxCodesParams,
    options?: UseTaxCodesOptions,
): UseTaxCodesResult {
    const moneyApi = useSdkClient(MoneyApi);
    const queryKey = queryKeys.money.taxCodes(params ?? {});

    const query = useQuery<TaxCodeListPayload>({
        queryKey,
        queryFn: async () => {
            const response = await moneyApi.listTaxCodes({
                cursor: params?.cursor,
                search: params?.search,
                active: params?.active,
                type: params?.type,
            });

            return {
                items: response.data.items ?? [],
                meta: response.meta,
            };
        },
        staleTime: 5 * 60 * 1000,
        enabled: options?.enabled ?? true,
    });

    const payload = useMemo<TaxCodeListPayload>(
        () => query.data ?? { items: [], meta: undefined },
        [query.data],
    );

    return {
        ...query,
        data: payload,
        items: payload.items,
        meta: payload.meta,
    } as UseTaxCodesResult;
}
