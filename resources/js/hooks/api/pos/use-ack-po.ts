import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { api } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { PurchaseOrderSummary } from '@/types/sourcing';
import { mapPurchaseOrder, type PurchaseOrderResponse } from '@/hooks/api/usePurchaseOrder';

export interface AckPoInput {
    poId: number;
    decision: 'acknowledged' | 'declined';
    reason?: string;
}

interface AckPoEnvelope {
    purchase_order?: PurchaseOrderResponse;
    data?: PurchaseOrderResponse;
    payload?: PurchaseOrderResponse;
}

function extractPurchaseOrder(payload: AckPoEnvelope | PurchaseOrderResponse | undefined): PurchaseOrderResponse | undefined {
    if (!payload) {
        return undefined;
    }

    if ('purchase_order' in payload && payload.purchase_order) {
        return payload.purchase_order;
    }

    if ('data' in payload && payload.data) {
        return payload.data;
    }

    if ('payload' in payload && payload.payload) {
        return payload.payload;
    }

    return payload as PurchaseOrderResponse;
}

export function useAckPo(): UseMutationResult<PurchaseOrderSummary | undefined, Error, AckPoInput> {
    const queryClient = useQueryClient();

    return useMutation<PurchaseOrderSummary | undefined, Error, AckPoInput>({
        mutationFn: async ({ poId, decision, reason }) => {
            if (!Number.isFinite(poId) || poId <= 0) {
                throw new Error('A valid purchase order id is required to respond.');
            }

            if (decision !== 'acknowledged' && decision !== 'declined') {
                throw new Error('Decision must be acknowledged or declined.');
            }

            const response = (await api.post<AckPoEnvelope | PurchaseOrderResponse>(
                `/purchase-orders/${poId}/acknowledge`,
                {
                    decision,
                    reason: reason?.trim() || undefined,
                },
            )) as AckPoEnvelope | PurchaseOrderResponse | undefined;

            const purchaseOrder = extractPurchaseOrder(response);

            return purchaseOrder ? mapPurchaseOrder(purchaseOrder) : undefined;
        },
        onSuccess: (_, { poId, decision }) => {
            publishToast({
                variant: 'success',
                title: decision === 'acknowledged' ? 'Purchase order acknowledged' : 'Purchase order declined',
                description:
                    decision === 'acknowledged'
                        ? 'Thanks for confirming the order. The buyer has been notified.'
                        : 'Decline recorded. The buyer will review and follow up.',
            });

            void queryClient.invalidateQueries({ queryKey: queryKeys.purchaseOrders.detail(poId) });
            void queryClient.invalidateQueries({ queryKey: queryKeys.purchaseOrders.root() });
            void queryClient.invalidateQueries({ queryKey: queryKeys.purchaseOrders.events(poId) });
        },
        onError: (error) => {
            publishToast({
                variant: 'destructive',
                title: 'Unable to record decision',
                description: error.message ?? 'Please refresh and try again.',
            });
        },
    });
}
