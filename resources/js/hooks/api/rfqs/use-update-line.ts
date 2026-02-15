import {
    useMutation,
    useQueryClient,
    type QueryKey,
} from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import {
    RFQsApi,
    type RequestMeta,
    type Rfq,
    type RfqItem,
    type RfqLinePayload,
} from '@/sdk';

export interface UpdateRfqLinePayload {
    rfqId: string | number;
    lineId: string | number;
    line: RfqLinePayload;
}

type LinesCache = {
    items: RfqItem[];
    meta?: RequestMeta;
};

interface UpdateLineContext {
    previousLines: Array<[QueryKey, LinesCache | undefined]>;
    previousRfq?: Rfq;
}

function resolveLineId(lineId: string | number): string {
    return String(lineId);
}

export function useUpdateLine() {
    const rfqsApi = useSdkClient(RFQsApi);
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ rfqId, lineId, line }: UpdateRfqLinePayload) => {
            const response = await rfqsApi.updateRfqLine({
                rfqId: String(rfqId),
                lineId: String(lineId),
                rfqLinePayload: line,
            });

            return response.data;
        },
        onMutate: async (variables): Promise<UpdateLineContext> => {
            const rfqId = String(variables.rfqId);
            const targetLineId = resolveLineId(variables.lineId);
            const linePrefix = queryKeys.rfqs.lines(rfqId);
            await queryClient.cancelQueries({ queryKey: linePrefix });
            await queryClient.cancelQueries({
                queryKey: queryKeys.rfqs.detail(rfqId),
            });

            const previousLines = queryClient.getQueriesData<LinesCache>({
                queryKey: linePrefix,
            });
            const previousRfq = queryClient.getQueryData<Rfq>(
                queryKeys.rfqs.detail(rfqId),
            );

            previousLines.forEach(([key, data]) => {
                const updatedItems = (data?.items ?? []).map((item) => {
                    const existing = item;
                    if (resolveLineId(item.id) !== targetLineId) {
                        return item;
                    }

                    return {
                        ...item,
                        partName: variables.line.partName,
                        spec: variables.line.spec ?? undefined,
                        quantity: variables.line.quantity,
                        uom: variables.line.uom ?? undefined,
                        targetPrice: variables.line.targetPrice ?? undefined,
                        requiredDate:
                            variables.line.requiredDate ??
                            existing.requiredDate,
                    };
                });

                queryClient.setQueryData<LinesCache>(key, {
                    ...data,
                    items: updatedItems,
                });
            });

            if (previousRfq) {
                const updatedItems = (previousRfq.items ?? []).map((item) => {
                    const existing = item;
                    if (resolveLineId(item.id) !== targetLineId) {
                        return item;
                    }

                    return {
                        ...item,
                        partName: variables.line.partName,
                        spec: variables.line.spec ?? undefined,
                        quantity: variables.line.quantity,
                        uom: variables.line.uom ?? undefined,
                        targetPrice: variables.line.targetPrice ?? undefined,
                        requiredDate:
                            variables.line.requiredDate ??
                            existing.requiredDate,
                    };
                });

                queryClient.setQueryData<Rfq>(queryKeys.rfqs.detail(rfqId), {
                    ...previousRfq,
                    items: updatedItems,
                });
            }

            return { previousLines, previousRfq } satisfies UpdateLineContext;
        },
        onError: (_error, variables, context) => {
            if (!context) {
                return;
            }

            context.previousLines.forEach(([key, data]) => {
                queryClient.setQueryData<LinesCache | undefined>(key, data);
            });

            if (context.previousRfq) {
                queryClient.setQueryData(
                    queryKeys.rfqs.detail(variables.rfqId),
                    context.previousRfq,
                );
            }
        },
        onSettled: (_response, _error, variables) => {
            const rfqId = String(variables.rfqId);
            void queryClient.invalidateQueries({
                queryKey: queryKeys.rfqs.detail(rfqId),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.rfqs.lines(rfqId),
            });
            void queryClient.invalidateQueries({
                queryKey: queryKeys.rfqs.timeline(rfqId),
            });
        },
    });
}
