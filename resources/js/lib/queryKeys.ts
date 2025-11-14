export const queryKeys = {
    suppliers: {
        list: (params?: Record<string, unknown>) => ['suppliers', 'list', params ?? {}] as const,
    },
    rfqs: {
        root: () => ['rfqs'] as const,
        list: (params?: Record<string, unknown>) => ['rfqs', 'list', params ?? {}] as const,
        detail: (id: string | number) => ['rfqs', 'detail', id] as const,
        quotes: (rfqId: string | number) => ['rfqs', 'quotes', rfqId] as const,
        invitations: (rfqId: string | number) => ['rfqs', 'invitations', rfqId] as const,
        lines: (rfqId: string | number) => ['rfqs', 'lines', rfqId] as const,
        suppliers: (rfqId: string | number) => ['rfqs', 'suppliers', rfqId] as const,
        clarifications: (rfqId: string | number) => ['rfqs', 'clarifications', rfqId] as const,
        timeline: (rfqId: string | number) => ['rfqs', 'timeline', rfqId] as const,
        attachments: (rfqId: string | number) => ['rfqs', 'attachments', rfqId] as const,
    },
    money: {
        settings: () => ['money', 'settings'] as const,
    },
    localization: {
        settings: () => ['localization', 'settings'] as const,
        convert: (params: { from: string; to: string }) => ['localization', 'convert', params] as const,
        uoms: (dimension?: string | null) => ['localization', 'uoms', dimension ?? 'all'] as const,
    },
    orders: {
        list: (params?: Record<string, unknown>) => ['orders', 'list', params ?? {}] as const,
    },
    purchaseOrders: {
        root: () => ['purchase-orders'] as const,
        list: (params?: Record<string, unknown>) => ['purchase-orders', 'list', params ?? {}] as const,
        detail: (id: number) => ['purchase-orders', 'detail', id] as const,
        changeOrders: (purchaseOrderId: number) => ['purchase-orders', 'change-orders', purchaseOrderId] as const,
    },
    companies: {
        detail: (id: number) => ['companies', 'detail', id] as const,
        documents: (id: number) => ['companies', 'documents', id] as const,
    },
    admin: {
        companies: (params?: Record<string, unknown>) => ['admin', 'companies', 'list', params ?? {}] as const,
    },
    me: {
        supplierStatus: () => ['me', 'supplier', 'status'] as const,
    },
};

export type QueryKey = ReturnType<
    | (typeof queryKeys)['suppliers']['list']
    | (typeof queryKeys)['rfqs']['root']
    | (typeof queryKeys)['rfqs']['list']
    | (typeof queryKeys)['rfqs']['detail']
    | (typeof queryKeys)['rfqs']['quotes']
    | (typeof queryKeys)['orders']['list']
    | (typeof queryKeys)['rfqs']['invitations']
    | (typeof queryKeys)['rfqs']['lines']
    | (typeof queryKeys)['rfqs']['suppliers']
    | (typeof queryKeys)['rfqs']['clarifications']
    | (typeof queryKeys)['rfqs']['timeline']
    | (typeof queryKeys)['rfqs']['attachments']
    | (typeof queryKeys)['money']['settings']
    | (typeof queryKeys)['localization']['settings']
    | (typeof queryKeys)['localization']['convert']
    | (typeof queryKeys)['localization']['uoms']
    | (typeof queryKeys)['purchaseOrders']['root']
    | (typeof queryKeys)['purchaseOrders']['list']
    | (typeof queryKeys)['purchaseOrders']['detail']
    | (typeof queryKeys)['purchaseOrders']['changeOrders']
    | (typeof queryKeys)['companies']['detail']
    | (typeof queryKeys)['companies']['documents']
    | (typeof queryKeys)['admin']['companies']
    | (typeof queryKeys)['me']['supplierStatus']
>;
