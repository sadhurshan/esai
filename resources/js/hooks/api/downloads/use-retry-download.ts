import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { DownloadJobSummary } from '@/types/downloads';

import { mapDownloadJob, type DownloadJobResponseItem } from './mappers';

export interface RetryDownloadVariables {
    jobId: number;
}

export function useRetryDownload(): UseMutationResult<
    DownloadJobSummary,
    ApiError,
    RetryDownloadVariables
> {
    const queryClient = useQueryClient();

    return useMutation<DownloadJobSummary, ApiError, RetryDownloadVariables>({
        mutationFn: async ({ jobId }) => {
            const response = (await api.post<DownloadJobResponseItem>(
                `/downloads/${jobId}/retry`,
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
