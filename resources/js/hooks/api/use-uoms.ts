import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { LocalizationApi } from '@/sdk';

export interface UomOption {
    code: string;
    name: string;
    symbol: string | null;
    dimension: string;
    siBase: boolean;
}

export interface UseUomsOptions {
    dimension?: string;
    enabled?: boolean;
}

function normalizeUoms(items: UomOption[]): UomOption[] {
    const unique = new Map<string, UomOption>();

    for (const item of items) {
        const code = item.code.trim();
        if (!unique.has(code)) {
            unique.set(code, {
                ...item,
                code,
                name: item.name.trim(),
                symbol: item.symbol ? item.symbol.trim() : null,
                dimension: item.dimension.trim(),
            });
        }
    }

    const result = Array.from(unique.values());

    result.sort((a, b) => {
        if (a.siBase && !b.siBase) {
            return -1;
        }
        if (!a.siBase && b.siBase) {
            return 1;
        }
        return a.name.localeCompare(b.name);
    });

    return result;
}

export function useUoms(
    options: UseUomsOptions = {},
): UseQueryResult<UomOption[]> {
    const localizationApi = useSdkClient(LocalizationApi);
    const { dimension, enabled } = options;

    return useQuery<UomOption[]>({
        queryKey: queryKeys.localization.uoms(dimension),
        queryFn: async () => {
            const response = await localizationApi.listUoms({ dimension });
            const items = response.data.items ?? [];

            return normalizeUoms(
                items.map((uom) => ({
                    code: uom.code,
                    name: uom.name,
                    symbol: uom.symbol ?? null,
                    dimension: uom.dimension,
                    siBase: Boolean(uom.siBase),
                })),
            );
        },
        staleTime: 10 * 60 * 1000,
        enabled,
    });
}
