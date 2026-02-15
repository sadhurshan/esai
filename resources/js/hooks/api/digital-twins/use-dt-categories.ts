import {
    useQuery,
    type UseQueryOptions,
    type UseQueryResult,
} from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { DigitalTwinLibraryApi, type DigitalTwinCategoryNode } from '@/sdk';

export type UseDigitalTwinCategoriesOptions = Pick<
    UseQueryOptions<DigitalTwinCategoryNode[]>,
    'enabled' | 'staleTime' | 'gcTime' | 'refetchOnWindowFocus'
>;

export type UseDigitalTwinCategoriesResult = UseQueryResult<
    DigitalTwinCategoryNode[]
> & {
    categories: DigitalTwinCategoryNode[];
};

export function useDigitalTwinCategories(
    options: UseDigitalTwinCategoriesOptions = {},
): UseDigitalTwinCategoriesResult {
    const api = useSdkClient(DigitalTwinLibraryApi);

    const query = useQuery<DigitalTwinCategoryNode[]>({
        queryKey: queryKeys.digitalTwins.categories(),
        queryFn: async () => {
            const response = await api.listDigitalTwins({
                per_page: 1,
                include: ['categories'],
            });

            return response.data?.categories ?? [];
        },
        staleTime: options.staleTime ?? 5 * 60_000,
        gcTime: options.gcTime,
        refetchOnWindowFocus: options.refetchOnWindowFocus ?? false,
        enabled: options.enabled ?? true,
    });

    const categories = query.data ?? [];

    return {
        ...query,
        categories,
    } satisfies UseDigitalTwinCategoriesResult;
}
