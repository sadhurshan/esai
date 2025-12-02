import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useApiClientContext } from '@/contexts/api-client-context';
import {
    ListRfqsSortDirectionEnum,
    ListRfqsSortEnum,
    ListRfqsStatusEnum,
    type ListRfqs200Response,
    type Rfq,
    ListRfqs200ResponseFromJSON,
} from '@/sdk';

export type RfqStatusFilter = 'all' | 'draft' | 'open' | 'closed' | 'awarded' | 'cancelled';

export interface UseRfqsParams {
    perPage?: number;
    cursor?: string;
    status?: RfqStatusFilter;
    search?: string;
    dueFrom?: string;
    dueTo?: string;
    openBidding?: boolean;
    method?: string;
}

export interface CursorState {
    nextCursor?: string;
    prevCursor?: string;
    perPage?: number;
}

export type UseRfqsResult = UseQueryResult<ListRfqs200Response, unknown> & {
    items: Rfq[];
    cursor?: CursorState;
};

const STATUS_MAP: Record<Exclude<RfqStatusFilter, 'all'>, ListRfqsStatusEnum> = {
    draft: ListRfqsStatusEnum.Draft,
    open: ListRfqsStatusEnum.Open,
    closed: ListRfqsStatusEnum.Closed,
    awarded: ListRfqsStatusEnum.Awarded,
    cancelled: ListRfqsStatusEnum.Cancelled,
};

function normalizeStatusFilters(status: RfqStatusFilter): ListRfqsStatusEnum[] | undefined {
    if (status === 'all') {
        return undefined;
    }

    return [STATUS_MAP[status]];
}

function extractCursorState(meta?: unknown): CursorState | undefined {
    if (!meta || typeof meta !== 'object') {
        return undefined;
    }

    const record = meta as Record<string, unknown>;

    const cursorFromRecord = readCursorMeta(record);

    if (cursorFromRecord) {
        return cursorFromRecord;
    }

    const cursor = record.cursor;
    if (cursor && typeof cursor === 'object') {
        const nestedCursor = readCursorMeta(cursor as Record<string, unknown>);
        if (nestedCursor) {
            return nestedCursor;
        }
    }

    const pagination = record.pagination;
    if (pagination && typeof pagination === 'object') {
        const perPage = readNumber(pagination as Record<string, unknown>, 'per_page', 'perPage');
        if (perPage !== undefined) {
            return { perPage };
        }
    }

    return undefined;
}

function readCursorMeta(record: Record<string, unknown>): CursorState | undefined {
    const nextCursor = readString(record, 'next_cursor', 'nextCursor');
    const prevCursor = readString(record, 'prev_cursor', 'prevCursor');
    const perPage = readNumber(record, 'per_page', 'perPage');

    if (nextCursor || prevCursor || perPage !== undefined) {
        return { nextCursor, prevCursor, perPage };
    }

    return undefined;
}

function readString(record: Record<string, unknown>, ...keys: string[]): string | undefined {
    for (const key of keys) {
        const value = record[key];
        if (typeof value === 'string') {
            return value;
        }
    }
    return undefined;
}

function readNumber(record: Record<string, unknown>, ...keys: string[]): number | undefined {
    for (const key of keys) {
        const value = record[key];
        if (typeof value === 'number') {
            return value;
        }
    }
    return undefined;
}

export function useRfqs(params: UseRfqsParams = {}): UseRfqsResult {
    const { configuration } = useApiClientContext();
    const {
        perPage = 25,
        status = 'all',
        search,
        dueFrom,
        dueTo,
        cursor,
        openBidding,
        method,
    } = params;

    const sanitizedSearch = search?.trim() || undefined;
    const statusFilters = normalizeStatusFilters(status);

    const query = useQuery<ListRfqs200Response>({
        queryKey: ['rfqs', { perPage, status, sanitizedSearch, dueFrom, dueTo, cursor, openBidding, method }],
        queryFn: async () => {
            const fetchApi = configuration.fetchApi ?? fetch;
            const queryString = buildQueryString({
                perPage,
                cursor,
                status: statusFilters,
                search: sanitizedSearch,
                dueFrom,
                dueTo,
                openBidding,
                method,
            });
            const url = `${configuration.basePath.replace(/\/$/, '')}/api/rfqs${queryString}`;
            const response = await fetchApi(url);
            const data = await response.json();
            return ListRfqs200ResponseFromJSON(data);
        },
        placeholderData: keepPreviousData,
    });

    const items = query.data?.data.items ?? [];
    const cursorState = extractCursorState(query.data?.meta);

    return {
        ...query,
        items,
        cursor: cursorState,
    } satisfies UseRfqsResult;
}

interface QueryBuilderInput {
    perPage: number;
    cursor?: string;
    status?: ListRfqsStatusEnum[];
    search?: string;
    dueFrom?: string;
    dueTo?: string;
    openBidding?: boolean;
    method?: string;
}

function buildQueryString(params: QueryBuilderInput): string {
    const searchParams = new URLSearchParams();
    searchParams.set('per_page', String(params.perPage));
    searchParams.set('sort', ListRfqsSortEnum.DueAt);
    searchParams.set('sort_direction', ListRfqsSortDirectionEnum.Asc);

    if (params.cursor) {
        searchParams.set('cursor', params.cursor);
    }

    if (params.search) {
        searchParams.set('search', params.search);
    }

    if (params.dueFrom) {
        searchParams.set('due_from', params.dueFrom);
    }

    if (params.dueTo) {
        searchParams.set('due_to', params.dueTo);
    }

    if (params.openBidding !== undefined) {
        searchParams.set('open_bidding', String(params.openBidding));
    }

    if (params.method) {
        searchParams.set('method', params.method);
    }

    if (params.status && params.status.length > 0) {
        searchParams.set('status', params.status.join(','));
    }

    const queryString = searchParams.toString();
    return queryString.length > 0 ? `?${queryString}` : '';
}
