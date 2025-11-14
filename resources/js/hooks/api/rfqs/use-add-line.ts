import { useMutation, useQueryClient, type QueryKey } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { RFQsApi, type RequestMeta, type Rfq, type RfqItem, type RfqLinePayload } from '@/sdk';

export interface AddRfqLinePayload {
    rfqId: string | number;
    line: RfqLinePayload;
}

type LinesCache = {
    items: RfqItem[];
    meta?: RequestMeta;
};

interface AddLineContext {
    previousLines: Array<[QueryKey, LinesCache | undefined]>;
    previousRfq?: Rfq;
}

function computeNextLineNo(items: RfqItem[], fallback = 1): number {
    if (items.length === 0) {
        return fallback;
    }

    return items.reduce((highest, item) => Math.max(highest, item.lineNo ?? 0), 0) + 1;
}

export function useAddLine() {
    const rfqsApi = useSdkClient(RFQsApi);
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ rfqId, line }: AddRfqLinePayload) => {
            const response = await rfqsApi.createRfqLine({
                rfqId: String(rfqId),
                rfqLinePayload: line,
            });

            return response.data;
        },
        onMutate: async (variables): Promise<AddLineContext> => {
            const rfqId = String(variables.rfqId);
            const linePrefix = queryKeys.rfqs.lines(rfqId);
            await queryClient.cancelQueries({ queryKey: linePrefix });
            await queryClient.cancelQueries({ queryKey: queryKeys.rfqs.detail(rfqId) });

            const previousLines = queryClient.getQueriesData<LinesCache>({ queryKey: linePrefix });
            const previousRfq = queryClient.getQueryData<Rfq>(queryKeys.rfqs.detail(rfqId));

            const baselineItems = Array.from(previousLines[0]?.[1]?.items ?? []);
            const optimisticLine: RfqItem = {
                id: `optimistic-${Date.now()}`,
                lineNo: computeNextLineNo(baselineItems),
                partName: variables.line.partName,
                spec: variables.line.spec ?? undefined,
                quantity: variables.line.quantity,
                uom: variables.line.uom ?? undefined,
                targetPrice: variables.line.targetPrice ?? undefined,
                requiredDate: variables.line.requiredDate ?? undefined,
            };

            previousLines.forEach(([key, data]) => {
                const existingItems = data?.items ?? [];
                queryClient.setQueryData<LinesCache>(key, {
                    ...data,
                    items: [...existingItems, optimisticLine],
                });
            });

            if (previousRfq) {
                const existingItems = previousRfq.items ?? [];
                queryClient.setQueryData<Rfq>(queryKeys.rfqs.detail(rfqId), {
                    ...previousRfq,
                    items: [...existingItems, optimisticLine],
                });
            }

            return { previousLines, previousRfq } satisfies AddLineContext;
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

