export const queryKeys = {
    suppliers: {
        list: (params?: Record<string, unknown>) => ['suppliers', 'list', params ?? {}] as const,
    },
    rfqs: {
        root: () => ['rfqs'] as const,
        list: (params?: Record<string, unknown>) => ['rfqs', 'list', params ?? {}] as const,
        detail: (id: number) => ['rfqs', 'detail', id] as const,
        quotes: (rfqId: number) => ['rfqs', 'quotes', rfqId] as const,
    },
    orders: {
        list: (params?: Record<string, unknown>) => ['orders', 'list', params ?? {}] as const,
    },
};

export type QueryKey = ReturnType<
    | (typeof queryKeys)['suppliers']['list']
    | (typeof queryKeys)['rfqs']['root']
    | (typeof queryKeys)['rfqs']['list']
    | (typeof queryKeys)['rfqs']['detail']
    | (typeof queryKeys)['rfqs']['quotes']
    | (typeof queryKeys)['orders']['list']
>;
