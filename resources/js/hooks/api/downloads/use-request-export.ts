import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type {
    DownloadDocumentType,
    DownloadFormat,
    DownloadJobSummary,
} from '@/types/downloads';

import { mapDownloadJob, type DownloadJobResponseItem } from './mappers';

export interface RequestExportVariables {
    documentType: DownloadDocumentType;
    documentId: number | string;
    format: DownloadFormat;
    reference?: string | null;
    meta?: Record<string, unknown>;
}

export function useRequestExport(): UseMutationResult<
    DownloadJobSummary,
    ApiError,
    RequestExportVariables
> {
    const queryClient = useQueryClient();

    return useMutation<DownloadJobSummary, ApiError, RequestExportVariables>({
        mutationFn: async (variables) => {
            const response = (await api.post<DownloadJobResponseItem>(
                '/downloads',
                {
                    document_type: variables.documentType,
                    document_id: variables.documentId,
                    format: variables.format,
                    reference: variables.reference,
                    meta: variables.meta,
                },
            )) as unknown as DownloadJobResponseItem;

            return mapDownloadJob(response);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: queryKeys.downloads.root(),
            });
        },
    });
}
