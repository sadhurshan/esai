import { useQuery, type UseQueryOptions, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { DigitalTwinLibraryApi, type DigitalTwinLibraryDetail, type DigitalTwinLibraryDetailResponse } from '@/sdk';

export type UseDigitalTwinOptions = Pick<
    UseQueryOptions<DigitalTwinLibraryDetailResponse>,
    'enabled' | 'staleTime' | 'gcTime' | 'refetchOnWindowFocus'
>;

export type UseDigitalTwinResult = UseQueryResult<DigitalTwinLibraryDetailResponse> & {
    digitalTwin?: DigitalTwinLibraryDetail;
};

function normalizeId(digitalTwinId?: number | string): string | undefined {
    if (digitalTwinId == null) {
        return undefined;
    }

    return String(digitalTwinId);
}

export function useDigitalTwin(
    digitalTwinId?: number | string,
    options: UseDigitalTwinOptions = {},
): UseDigitalTwinResult {
    const api = useSdkClient(DigitalTwinLibraryApi);
    const resolvedId = normalizeId(digitalTwinId);
    const queryKey = queryKeys.digitalTwins.libraryDetail(resolvedId ?? 'pending');

    const query = useQuery<DigitalTwinLibraryDetailResponse>({
        queryKey,
        queryFn: async () => {
            if (!resolvedId) {
                throw new Error('digitalTwinId is required');
            }

            return api.getDigitalTwin(resolvedId);
        },
        enabled: Boolean(resolvedId) && (options.enabled ?? true),
        staleTime: options.staleTime ?? 60_000,
        gcTime: options.gcTime,
        refetchOnWindowFocus: options.refetchOnWindowFocus ?? false,
    });

    const digitalTwin = query.data?.data?.digital_twin;

    return {
        ...query,
        digitalTwin,
    } satisfies UseDigitalTwinResult;
}
