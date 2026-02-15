import {
    keepPreviousData,
    useQuery,
    type UseQueryResult,
} from '@tanstack/react-query';

import { api, buildQuery, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type {
    DownloadDocumentType,
    DownloadFormat,
    DownloadJobListResult,
    DownloadJobStatus,
} from '@/types/downloads';

import {
    mapDownloadJob,
    mapDownloadMeta,
    type DownloadJobResponseItem,
    type DownloadJobResponseMeta,
} from './mappers';

interface DownloadJobIndexResponse {
    items: DownloadJobResponseItem[];
    meta?: DownloadJobResponseMeta | null;
}

export interface UseDownloadsParams {
    cursor?: string | null;
    perPage?: number;
    status?: DownloadJobStatus;
    format?: DownloadFormat;
    documentType?: DownloadDocumentType;
}

export interface UseDownloadsOptions {
    enabled?: boolean;
    refetchInterval?: number;
}

const DEFAULT_REFETCH_INTERVAL = 15_000;

export function useDownloads(
    filters: UseDownloadsParams = {},
    options: UseDownloadsOptions = {},
): UseQueryResult<DownloadJobListResult, ApiError> {
    const queryParams: Record<string, unknown> = {};

    if (filters.cursor) {
        queryParams.cursor = filters.cursor;
    }

    if (filters.perPage) {
        queryParams.per_page = filters.perPage;
    }

    if (filters.status) {
        queryParams.status = filters.status;
    }

    if (filters.format) {
        queryParams.format = filters.format;
    }

    if (filters.documentType) {
        queryParams.document_type = filters.documentType;
    }

    const refetchInterval = options.refetchInterval ?? DEFAULT_REFETCH_INTERVAL;

    return useQuery<DownloadJobIndexResponse, ApiError, DownloadJobListResult>({
        queryKey: queryKeys.downloads.list(queryParams),
        queryFn: async () => {
            const query = buildQuery(queryParams);
            return (await api.get<DownloadJobIndexResponse>(
                `/downloads${query}`,
            )) as unknown as DownloadJobIndexResponse;
        },
        select: (response) => ({
            items: (response.items ?? []).map(mapDownloadJob),
            meta: mapDownloadMeta(response.meta),
        }),
        placeholderData: keepPreviousData,
        staleTime: 10_000,
        gcTime: 60_000,
        refetchInterval,
        refetchIntervalInBackground: true,
        enabled: options.enabled ?? true,
    });
}
