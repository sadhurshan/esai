import { useMutation, useQueryClient, type QueryKey } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { RFQsApi, type RequestMeta, type Rfq, type RfqItem } from '@/sdk';

export interface DeleteRfqLinePayload {
    rfqId: string | number;
    lineId: string | number;
}

type LinesCache = {
    items: RfqItem[];
    meta?: RequestMeta;
};

interface DeleteLineContext {
    previousLines: Array<[QueryKey, LinesCache | undefined]>;
    previousRfq?: Rfq;
}

function resolveLineId(lineId: string | number): string {
    return String(lineId);
}

export function useDeleteLine() {
    const rfqsApi = useSdkClient(RFQsApi);
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ rfqId, lineId }: DeleteRfqLinePayload) => {
            const response = await rfqsApi.deleteRfqLine({
                rfqId: String(rfqId),
                lineId: String(lineId),
            });

            return response.data;
        },
        onMutate: async (variables): Promise<DeleteLineContext> => {
            const rfqId = String(variables.rfqId);
            const targetLineId = resolveLineId(variables.lineId);
            const linePrefix = queryKeys.rfqs.lines(rfqId);
            await queryClient.cancelQueries({ queryKey: linePrefix });
            await queryClient.cancelQueries({ queryKey: queryKeys.rfqs.detail(rfqId) });

            const previousLines = queryClient.getQueriesData<LinesCache>({ queryKey: linePrefix });
            const previousRfq = queryClient.getQueryData<Rfq>(queryKeys.rfqs.detail(rfqId));

            previousLines.forEach(([key, data]) => {
                const filteredItems = (data?.items ?? []).filter((item) => resolveLineId(item.id) !== targetLineId);
                queryClient.setQueryData<LinesCache>(key, {
                    ...data,
                    items: filteredItems,
                });
            });

            if (previousRfq) {
                const filteredItems = (previousRfq.items ?? []).filter((item) => resolveLineId(item.id) !== targetLineId);
                queryClient.setQueryData<Rfq>(queryKeys.rfqs.detail(rfqId), {
                    ...previousRfq,
                    items: filteredItems,
                });
            }

            return { previousLines, previousRfq } satisfies DeleteLineContext;
        },
        onError: (_error, variables, context) => {
            if (!context) {
                return;
            }

            context.previousLines.forEach(([key, data]) => {
                queryClient.setQueryData<LinesCache | undefined>(key, data);
            });

            if (context.previousRfq) {
                queryClient.setQueryData(queryKeys.rfqs.detail(variables.rfqId), context.previousRfq);
            }
        },
        onSettled: (_response, _error, variables) => {
            const rfqId = String(variables.rfqId);
            void queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.detail(rfqId) });
            void queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.lines(rfqId) });
            void queryClient.invalidateQueries({ queryKey: queryKeys.rfqs.timeline(rfqId) });
        },
    });
}
