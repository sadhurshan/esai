import {
    useInfiniteQuery,
    type InfiniteData,
    type UseInfiniteQueryResult,
} from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import {
    AdminDigitalTwinApi,
    type AdminDigitalTwinAuditEvent,
    type CursorMetaResponse,
} from '@/sdk';

type AdminAuditEventsQueryKey = ReturnType<
    typeof queryKeys.digitalTwins.adminAuditEvents
>;

interface UseAdminDigitalTwinAuditEventsOptions {
    enabled?: boolean;
    perPage?: number;
}

interface AuditEventPage {
    items: AdminDigitalTwinAuditEvent[];
    meta: CursorMetaResponse | null;
}

export type UseAdminDigitalTwinAuditEventsResult =
    UseInfiniteQueryResult<AuditEventPage> & {
        events: AdminDigitalTwinAuditEvent[];
    };

export function useAdminDigitalTwinAuditEvents(
    digitalTwinId?: string | number,
    options: UseAdminDigitalTwinAuditEventsOptions = {},
): UseAdminDigitalTwinAuditEventsResult {
    const api = useSdkClient(AdminDigitalTwinApi);
    const perPage = options.perPage ?? 25;

    const query = useInfiniteQuery<
        AuditEventPage,
        Error,
        AuditEventPage,
        AdminAuditEventsQueryKey,
        string | undefined
    >({
        queryKey: queryKeys.digitalTwins.adminAuditEvents(
            digitalTwinId ?? 'unknown',
        ),
        enabled: Boolean(digitalTwinId) && (options.enabled ?? true),
        initialPageParam: undefined,
        queryFn: async ({ pageParam }) => {
            if (!digitalTwinId) {
                return { items: [], meta: null };
            }

            const response = await api.listDigitalTwinAuditEvents(
                digitalTwinId,
                {
                    cursor: pageParam,
                    per_page: perPage,
                },
            );

            return {
                items: response.data?.items ?? [],
                meta: response.data?.meta ?? null,
            } satisfies AuditEventPage;
        },
        getNextPageParam: (lastPage) => lastPage.meta?.next_cursor ?? undefined,
    });

    const pages =
        (
            query.data as
                | InfiniteData<AuditEventPage, string | undefined>
                | undefined
        )?.pages ?? [];
    const events = pages.flatMap((page: AuditEventPage) => page.items);

    return {
        ...query,
        events,
    } satisfies UseAdminDigitalTwinAuditEventsResult;
}
