import {
    useQuery,
    type UseQueryOptions,
    type UseQueryResult,
} from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import {
    AdminDigitalTwinApi,
    type AdminDigitalTwinDetail,
    type AdminDigitalTwinDetailResponse,
} from '@/sdk';

export type UseAdminDigitalTwinOptions = Pick<
    UseQueryOptions<AdminDigitalTwinDetailResponse>,
    'enabled' | 'staleTime' | 'gcTime' | 'refetchOnWindowFocus'
>;

export type UseAdminDigitalTwinResult =
    UseQueryResult<AdminDigitalTwinDetailResponse> & {
        digitalTwin?: AdminDigitalTwinDetail;
    };

function normalizeId(digitalTwinId?: number | string): string | undefined {
    if (digitalTwinId == null) {
        return undefined;
    }

    return String(digitalTwinId);
}

export function useAdminDigitalTwin(
    digitalTwinId?: number | string,
    options: UseAdminDigitalTwinOptions = {},
): UseAdminDigitalTwinResult {
    const api = useSdkClient(AdminDigitalTwinApi);
    const resolvedId = normalizeId(digitalTwinId);
    const queryKey = queryKeys.digitalTwins.adminDetail(
        resolvedId ?? 'pending',
    );

    const query = useQuery<AdminDigitalTwinDetailResponse>({
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
    } satisfies UseAdminDigitalTwinResult;
}
