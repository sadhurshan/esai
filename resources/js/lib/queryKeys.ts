export const queryKeys = {
    quotes: {
        root: () => ['quotes'] as const,
        rfq: (rfqId: string | number) => ['quotes', 'rfq', String(rfqId)] as const,
        list: (rfqId: string | number, filters?: Record<string, unknown>) =>
            ['quotes', 'rfq', String(rfqId), 'list', filters ?? {}] as const,
        detail: (quoteId: string | number) => ['quotes', 'detail', String(quoteId)] as const,
        lines: (quoteId: string | number) => ['quotes', 'lines', String(quoteId)] as const,
        revisions: (rfqId: string | number, quoteId: string | number) =>
            ['quotes', 'revisions', String(rfqId), String(quoteId)] as const,
        supplierRoot: () => ['quotes', 'supplier'] as const,
        supplierList: (params?: Record<string, unknown>) => ['quotes', 'supplier', 'list', params ?? {}] as const,
    },
    awards: {
        root: () => ['awards'] as const,
        candidates: (rfqId: string | number) => ['awards', 'rfq', String(rfqId), 'candidates'] as const,
        summary: (rfqId: string | number) => ['awards', 'rfq', String(rfqId), 'summary'] as const,
    },
    suppliers: {
        list: (params?: Record<string, unknown>) => ['suppliers', 'list', params ?? {}] as const,
        detail: (id: string | number) => ['suppliers', 'detail', String(id)] as const,
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
    settings: {
        company: () => ['settings', 'company'] as const,
        localization: () => ['settings', 'localization'] as const,
        numbering: () => ['settings', 'numbering'] as const,
    },
    orders: {
        list: (params?: Record<string, unknown>) => ['orders', 'list', params ?? {}] as const,
    },
    purchaseOrders: {
        root: () => ['purchase-orders'] as const,
        list: (params?: Record<string, unknown>) => ['purchase-orders', 'list', params ?? {}] as const,
        detail: (id: number) => ['purchase-orders', 'detail', id] as const,
        changeOrders: (purchaseOrderId: number) => ['purchase-orders', 'change-orders', purchaseOrderId] as const,
        events: (purchaseOrderId: number) => ['purchase-orders', 'events', purchaseOrderId] as const,
    },
    invoices: {
        list: (params?: Record<string, unknown>) => ['invoices', 'list', params ?? {}] as const,
        detail: (id: number | string) => ['invoices', 'detail', String(id)] as const,
    },
    companies: {
        detail: (id: number) => ['companies', 'detail', id] as const,
        documents: (id: number) => ['companies', 'documents', id] as const,
    },
    receiving: {
        root: () => ['receiving'] as const,
        list: (params?: Record<string, unknown>) => ['receiving', 'grns', 'list', params ?? {}] as const,
        detail: (id: string | number) => ['receiving', 'grns', 'detail', String(id)] as const,
    },
    matching: {
        candidates: (params?: Record<string, unknown>) => ['matching', 'candidates', params ?? {}] as const,
    },
    credits: {
        root: () => ['credits'] as const,
        list: (params?: Record<string, unknown>) => ['credits', 'list', params ?? {}] as const,
        detail: (id: string | number) => ['credits', 'detail', String(id)] as const,
    },
    inventory: {
        root: () => ['inventory'] as const,
        items: (params?: Record<string, unknown>) => ['inventory', 'items', params ?? {}] as const,
        item: (id: string | number) => ['inventory', 'items', String(id)] as const,
        locations: (params?: Record<string, unknown>) => ['inventory', 'locations', params ?? {}] as const,
        movementsList: (params?: Record<string, unknown>) => ['inventory', 'movements', params ?? {}] as const,
        movement: (id: string | number) => ['inventory', 'movements', String(id)] as const,
        lowStock: (params?: Record<string, unknown>) => ['inventory', 'low-stock', params ?? {}] as const,
    },
    admin: {
        companyApprovals: () => ['admin', 'company-approvals'] as const,
        analyticsOverview: () => ['admin', 'analytics', 'overview'] as const,
        companies: (params?: Record<string, unknown>) => ['admin', 'companies', 'list', params ?? {}] as const,
        plans: () => ['admin', 'plans'] as const,
        plan: (id: string | number) => ['admin', 'plans', String(id)] as const,
        roles: () => ['admin', 'roles'] as const,
        apiKeys: () => ['admin', 'api-keys'] as const,
        webhooks: () => ['admin', 'webhooks'] as const,
        webhookDeliveries: (subscriptionId: string, params?: Record<string, unknown>) =>
            ['admin', 'webhooks', subscriptionId, 'deliveries', params ?? {}] as const,
        rateLimits: () => ['admin', 'rate-limits'] as const,
        auditLog: (filters?: Record<string, unknown>) => ['admin', 'audit-log', filters ?? {}] as const,
    },
    me: {
        supplierStatus: () => ['me', 'supplier', 'status'] as const,
    },
};

export type QueryKey = ReturnType<
    | (typeof queryKeys)['suppliers']['list']
    | (typeof queryKeys)['suppliers']['detail']
    | (typeof queryKeys)['quotes']['root']
    | (typeof queryKeys)['quotes']['rfq']
    | (typeof queryKeys)['quotes']['list']
    | (typeof queryKeys)['quotes']['detail']
    | (typeof queryKeys)['quotes']['lines']
    | (typeof queryKeys)['quotes']['revisions']
    | (typeof queryKeys)['quotes']['supplierRoot']
    | (typeof queryKeys)['quotes']['supplierList']
    | (typeof queryKeys)['awards']['root']
    | (typeof queryKeys)['awards']['candidates']
    | (typeof queryKeys)['awards']['summary']
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
    | (typeof queryKeys)['settings']['company']
    | (typeof queryKeys)['settings']['localization']
    | (typeof queryKeys)['settings']['numbering']
    | (typeof queryKeys)['purchaseOrders']['root']
    | (typeof queryKeys)['purchaseOrders']['list']
    | (typeof queryKeys)['purchaseOrders']['detail']
    | (typeof queryKeys)['purchaseOrders']['changeOrders']
    | (typeof queryKeys)['purchaseOrders']['events']
    | (typeof queryKeys)['invoices']['list']
    | (typeof queryKeys)['invoices']['detail']
    | (typeof queryKeys)['companies']['detail']
    | (typeof queryKeys)['companies']['documents']
    | (typeof queryKeys)['receiving']['root']
    | (typeof queryKeys)['receiving']['list']
    | (typeof queryKeys)['receiving']['detail']
    | (typeof queryKeys)['matching']['candidates']
    | (typeof queryKeys)['credits']['root']
    | (typeof queryKeys)['credits']['list']
    | (typeof queryKeys)['credits']['detail']
    | (typeof queryKeys)['inventory']['root']
    | (typeof queryKeys)['inventory']['items']
    | (typeof queryKeys)['inventory']['item']
    | (typeof queryKeys)['inventory']['locations']
    | (typeof queryKeys)['inventory']['movementsList']
    | (typeof queryKeys)['inventory']['movement']
    | (typeof queryKeys)['inventory']['lowStock']
    | (typeof queryKeys)['admin']['analyticsOverview']
    | (typeof queryKeys)['admin']['companies']
    | (typeof queryKeys)['admin']['companyApprovals']
    | (typeof queryKeys)['admin']['plans']
    | (typeof queryKeys)['admin']['plan']
    | (typeof queryKeys)['admin']['roles']
    | (typeof queryKeys)['admin']['apiKeys']
    | (typeof queryKeys)['admin']['webhooks']
    | (typeof queryKeys)['admin']['webhookDeliveries']
    | (typeof queryKeys)['admin']['rateLimits']
    | (typeof queryKeys)['admin']['auditLog']
    | (typeof queryKeys)['me']['supplierStatus']
>;
