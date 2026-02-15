import { queryKeys } from '@/lib/queryKeys';
import type { QueryClient } from '@tanstack/react-query';

export function invalidateOrderCaches(
    queryClient: QueryClient,
    options: {
        orderId?: string | number | null;
        purchaseOrderId?: string | number | null;
    } = {},
): void {
    void queryClient.invalidateQueries({
        queryKey: queryKeys.orders.supplierList(),
    });
    void queryClient.invalidateQueries({
        queryKey: queryKeys.orders.buyerList(),
    });

    if (options.orderId !== undefined && options.orderId !== null) {
        const key = String(options.orderId);
        void queryClient.invalidateQueries({
            queryKey: queryKeys.orders.supplierDetail(key),
        });
        void queryClient.invalidateQueries({
            queryKey: queryKeys.orders.buyerDetail(key),
        });
    }

    if (
        options.purchaseOrderId !== undefined &&
        options.purchaseOrderId !== null
    ) {
        const poId = Number(options.purchaseOrderId);
        if (!Number.isNaN(poId)) {
            void queryClient.invalidateQueries({
                queryKey: queryKeys.purchaseOrders.detail(poId),
            });
        }
    }

    void queryClient.invalidateQueries({
        queryKey: queryKeys.purchaseOrders.list({}),
    });
}
