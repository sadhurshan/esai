import {
    useQuery,
    type UseQueryOptions,
    type UseQueryResult,
} from '@tanstack/react-query';
import { useMemo } from 'react';

import { useSdkClient } from '@/contexts/api-client-context';
import { toCursorMeta, type CursorPaginationMeta } from '@/lib/pagination';
import { queryKeys } from '@/lib/queryKeys';
import {
    DigitalTwinCategoryNode,
    DigitalTwinLibraryApi,
    DigitalTwinLibraryIndexResponse,
    DigitalTwinLibraryListItem,
} from '@/sdk';

export type DigitalTwinLibrarySort = 'relevance' | 'updated_at' | 'title';

export interface UseDigitalTwinsFilters {
    cursor?: string | null;
    perPage?: number;
    q?: string;
    categoryId?: number;
    tag?: string;
    tags?: string[];
    hasAsset?: string;
    hasAssets?: string[];
    updatedFrom?: string;
    updatedTo?: string;
    sort?: DigitalTwinLibrarySort;
    includeCategories?: boolean;
}

export type UseDigitalTwinsOptions = Pick<
    UseQueryOptions<DigitalTwinLibraryIndexResponse>,
    'enabled' | 'staleTime' | 'gcTime' | 'refetchOnWindowFocus'
>;

export interface UseDigitalTwinsResult {
    items: DigitalTwinLibraryListItem[];
    categories: DigitalTwinCategoryNode[];
    meta?: CursorPaginationMeta;
}

interface NormalizedDigitalTwinFilters {
    cursor?: string;
    perPage?: number;
    q?: string;
    categoryId?: number;
    tag?: string;
    tags?: string[];
    hasAsset?: string;
    hasAssets?: string[];
    updatedFrom?: string;
    updatedTo?: string;
    sort?: DigitalTwinLibrarySort;
    includeCategories: boolean;
}

function sanitizeStringArray(
    values?: string[] | null,
    transform?: (value: string) => string,
): string[] | undefined {
    if (!values) {
        return undefined;
    }

    const normalized = values
        .map((value) => (typeof value === 'string' ? value.trim() : ''))
        .filter((value): value is string => value.length > 0)
        .map((value) => (transform ? transform(value) : value));

    if (normalized.length === 0) {
        return undefined;
    }

    return Array.from(new Set(normalized));
}

function normalizeFilters(
    filters: UseDigitalTwinsFilters,
): NormalizedDigitalTwinFilters {
    const normalizedQuery = filters.q?.trim();
    const normalizedTag = filters.tag?.trim();
    const normalizedTags = sanitizeStringArray(filters.tags);
    const normalizedHasAssets = sanitizeStringArray(
        filters.hasAssets,
        (value) => value.toUpperCase(),
    );
    const normalizedHasAsset =
        typeof filters.hasAsset === 'string'
            ? filters.hasAsset.trim().toUpperCase()
            : undefined;

    return {
        cursor: filters.cursor ?? undefined,
        perPage:
            typeof filters.perPage === 'number' ? filters.perPage : undefined,
        q:
            normalizedQuery && normalizedQuery.length > 0
                ? normalizedQuery
                : undefined,
        categoryId:
            typeof filters.categoryId === 'number'
                ? filters.categoryId
                : undefined,
        tag:
            normalizedTag && normalizedTag.length > 0
                ? normalizedTag
                : undefined,
        tags: normalizedTags,
        hasAsset: normalizedHasAsset,
        hasAssets: normalizedHasAssets,
        updatedFrom: filters.updatedFrom ?? undefined,
        updatedTo: filters.updatedTo ?? undefined,
        sort: filters.sort,
        includeCategories: filters.includeCategories ?? false,
    };
}

type UseDigitalTwinsQueryResult =
    UseQueryResult<DigitalTwinLibraryIndexResponse>;

export function useDigitalTwins(
    filters: UseDigitalTwinsFilters = {},
    options: UseDigitalTwinsOptions = {},
): UseDigitalTwinsResult & UseDigitalTwinsQueryResult {
    const api = useSdkClient(DigitalTwinLibraryApi);

    const normalizedFilters = useMemo(
        () => normalizeFilters(filters),
        [filters],
    );

    const queryKeyFilters = useMemo(
        () => ({
            cursor: normalizedFilters.cursor,
            perPage: normalizedFilters.perPage,
            q: normalizedFilters.q,
            categoryId: normalizedFilters.categoryId,
            tag: normalizedFilters.tag,
            tags: normalizedFilters.tags,
            hasAsset: normalizedFilters.hasAsset,
            hasAssets: normalizedFilters.hasAssets,
            updatedFrom: normalizedFilters.updatedFrom,
            updatedTo: normalizedFilters.updatedTo,
            sort: normalizedFilters.sort,
            includeCategories: normalizedFilters.includeCategories,
        }),
        [normalizedFilters],
    );

    const query = useQuery<DigitalTwinLibraryIndexResponse>({
        queryKey: queryKeys.digitalTwins.libraryList(queryKeyFilters),
        queryFn: async () => {
            return api.listDigitalTwins({
                cursor: normalizedFilters.cursor ?? undefined,
                per_page: normalizedFilters.perPage,
                q: normalizedFilters.q,
                category_id: normalizedFilters.categoryId,
                tag: normalizedFilters.tag,
                tags: normalizedFilters.tags,
                has_asset: normalizedFilters.hasAsset,
                has_assets: normalizedFilters.hasAssets,
                updated_from: normalizedFilters.updatedFrom,
                updated_to: normalizedFilters.updatedTo,
                sort: normalizedFilters.sort,
                include: normalizedFilters.includeCategories
                    ? ['categories']
                    : undefined,
            });
        },
        staleTime: options.staleTime ?? 30_000,
        gcTime: options.gcTime,
        enabled: options.enabled ?? true,
        refetchOnWindowFocus: options.refetchOnWindowFocus ?? false,
    });

    const payload = query.data?.data ?? null;
    const rawMeta = (payload?.meta ?? undefined) as
        | Record<string, unknown>
        | undefined;
    const meta = toCursorMeta(rawMeta);
    const items = payload?.items ?? [];
    const categories = normalizedFilters.includeCategories
        ? (payload?.categories ?? [])
        : [];

    return {
        ...query,
        items,
        categories,
        meta,
    } satisfies UseDigitalTwinsResult & UseDigitalTwinsQueryResult;
}
