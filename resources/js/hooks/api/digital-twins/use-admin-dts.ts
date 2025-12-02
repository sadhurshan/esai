import { useMemo } from 'react';
import { useQuery, type UseQueryOptions, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { toCursorMeta, type CursorPaginationMeta } from '@/lib/pagination';
import { AdminDigitalTwinApi, type AdminDigitalTwinIndexResponse, type AdminDigitalTwinListItem } from '@/sdk';

export interface UseAdminDigitalTwinFilters {
	cursor?: string | null;
	perPage?: number;
	status?: string;
	categoryId?: number;
	q?: string;
}

export type UseAdminDigitalTwinsOptions = Pick<
	UseQueryOptions<AdminDigitalTwinIndexResponse>,
	'enabled' | 'staleTime' | 'gcTime' | 'refetchOnWindowFocus'
>;

export interface UseAdminDigitalTwinsResult {
	items: AdminDigitalTwinListItem[];
	meta?: CursorPaginationMeta;
}

type UseAdminDigitalTwinsQueryResult = UseQueryResult<AdminDigitalTwinIndexResponse>;

export function useAdminDigitalTwins(
	filters: UseAdminDigitalTwinFilters = {},
	options: UseAdminDigitalTwinsOptions = {},
): UseAdminDigitalTwinsResult & UseAdminDigitalTwinsQueryResult {
	const api = useSdkClient(AdminDigitalTwinApi);

	const normalizedFilters = useMemo(() => ({
		cursor: filters.cursor ?? undefined,
		perPage: typeof filters.perPage === 'number' ? filters.perPage : undefined,
		status: filters.status?.trim() ?? undefined,
		categoryId: typeof filters.categoryId === 'number' ? filters.categoryId : undefined,
		q: filters.q?.trim() ?? undefined,
	}), [filters.cursor, filters.perPage, filters.status, filters.categoryId, filters.q]);

	const queryKeyFilters = useMemo(
		() => ({
			cursor: normalizedFilters.cursor,
			perPage: normalizedFilters.perPage,
			status: normalizedFilters.status,
			categoryId: normalizedFilters.categoryId,
			q: normalizedFilters.q,
		}),
		[normalizedFilters],
	);

	const query = useQuery<AdminDigitalTwinIndexResponse>({
		queryKey: queryKeys.digitalTwins.adminList(queryKeyFilters),
		queryFn: async () => {
			return api.listDigitalTwins({
				cursor: normalizedFilters.cursor,
				per_page: normalizedFilters.perPage,
				status: normalizedFilters.status,
				category_id: normalizedFilters.categoryId,
				q: normalizedFilters.q,
			});
		},
		staleTime: options.staleTime ?? 30_000,
		gcTime: options.gcTime,
		enabled: options.enabled ?? true,
		refetchOnWindowFocus: options.refetchOnWindowFocus ?? false,
	});

	const payload = query.data?.data ?? null;
	const meta = toCursorMeta(payload?.meta as Record<string, unknown> | undefined);
	const items = payload?.items ?? [];

	return {
		...query,
		items,
		meta,
	} satisfies UseAdminDigitalTwinsResult & UseAdminDigitalTwinsQueryResult;
}
