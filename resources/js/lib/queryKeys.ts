export const queryKeys = {
    ai: {
        workflows: {
            list: (params?: Record<string, unknown>) => ['ai', 'workflows', 'list', params ?? {}] as const,
            step: (workflowId: string) => ['ai', 'workflows', 'step', workflowId] as const,
        },
        chat: {
            root: () => ['ai', 'chat'] as const,
            threads: (params?: Record<string, unknown>) => ['ai', 'chat', 'threads', params ?? {}] as const,
            thread: (threadId: string | number) => ['ai', 'chat', 'threads', 'detail', String(threadId)] as const,
        },
    },
    dashboard: {
        metrics: () => ['dashboard', 'buyer', 'metrics'] as const,
        supplierMetrics: () => ['dashboard', 'supplier', 'metrics'] as const,
    },
    quotes: {
        root: () => ['quotes'] as const,
        rfq: (rfqId: string | number) => ['quotes', 'rfq', String(rfqId)] as const,
        list: (rfqId: string | number, filters?: Record<string, unknown>) =>
            ['quotes', 'rfq', String(rfqId), 'list', filters ?? {}] as const,
        compare: (rfqId: string | number) => ['quotes', 'rfq', String(rfqId), 'compare'] as const,
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
    rfps: {
        root: () => ['rfps'] as const,
        list: (params?: Record<string, unknown>) => ['rfps', 'list', params ?? {}] as const,
        detail: (id: string | number) => ['rfps', 'detail', String(id)] as const,
        proposals: (id: string | number) => ['rfps', 'detail', String(id), 'proposals'] as const,
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
        taxCodes: (params?: Record<string, unknown>) => ['money', 'tax-codes', params ?? {}] as const,
    },
    localization: {
        settings: () => ['localization', 'settings'] as const,
        convert: (params: { from: string; to: string }) => ['localization', 'convert', params] as const,
        uoms: (dimension?: string | null) => ['localization', 'uoms', dimension ?? 'all'] as const,
    },
    settings: {
        company: () => ['settings', 'company'] as const,
        ai: () => ['settings', 'ai'] as const,
        localization: () => ['settings', 'localization'] as const,
        numbering: () => ['settings', 'numbering'] as const,
        notificationPreferences: () => ['settings', 'notification-preferences'] as const,
    },
    companyInvitations: {
        list: (params?: Record<string, unknown>) => ['company-invitations', 'list', params ?? {}] as const,
    },
    companyMembers: {
        list: (params?: Record<string, unknown>) => ['company-members', 'list', params ?? {}] as const,
    },
    companyRoleTemplates: {
        list: (params?: Record<string, unknown>) => ['company-role-templates', 'list', params ?? {}] as const,
    },
    orders: {
        list: (params?: Record<string, unknown>) => ['orders', 'list', params ?? {}] as const,
        supplierList: (params?: Record<string, unknown>) => ['orders', 'supplier', 'list', params ?? {}] as const,
        supplierDetail: (id: string | number) => ['orders', 'supplier', 'detail', String(id)] as const,
        buyerList: (params?: Record<string, unknown>) => ['orders', 'buyer', 'list', params ?? {}] as const,
        buyerDetail: (id: string | number) => ['orders', 'buyer', 'detail', String(id)] as const,
    },
    downloads: {
        root: () => ['downloads'] as const,
        list: (params?: Record<string, unknown>) => ['downloads', 'list', params ?? {}] as const,
    },
    analytics: {
        overview: () => ['analytics', 'overview'] as const,
        forecastReport: (params?: Record<string, unknown>) => ['analytics', 'forecast-report', params ?? {}] as const,
        supplierPerformanceReport: (params?: Record<string, unknown>) =>
            ['analytics', 'supplier-performance-report', params ?? {}] as const,
        supplierOptions: (params?: Record<string, unknown>) => ['analytics', 'supplier-options', params ?? {}] as const,
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
        supplierList: (params?: Record<string, unknown>) => ['invoices', 'supplier', 'list', params ?? {}] as const,
        supplierDetail: (id: number | string) => ['invoices', 'supplier', 'detail', String(id)] as const,
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
    notifications: {
        list: (params?: Record<string, unknown>) => ['notifications', 'list', params ?? {}] as const,
        badge: () => ['notifications', 'badge'] as const,
    },
    risk: {
        root: () => ['risk'] as const,
        list: (params?: Record<string, unknown>) => ['risk', 'list', params ?? {}] as const,
        detail: (supplierId: string | number) => ['risk', 'detail', String(supplierId)] as const,
    },
    events: {
        deliveries: (params?: Record<string, unknown>) => ['events', 'deliveries', params ?? {}] as const,
    },
    credits: {
        root: () => ['credits'] as const,
        list: (params?: Record<string, unknown>) => ['credits', 'list', params ?? {}] as const,
        detail: (id: string | number) => ['credits', 'detail', String(id)] as const,
    },
    digitalTwins: {
        libraryRoot: () => ['digital-twins', 'library'] as const,
        libraryList: (params?: Record<string, unknown>) => ['digital-twins', 'library', 'list', params ?? {}] as const,
        libraryDetail: (id: string | number) => ['digital-twins', 'library', 'detail', String(id)] as const,
        categories: () => ['digital-twins', 'library', 'categories'] as const,
        adminRoot: () => ['digital-twins', 'admin'] as const,
        adminList: (params?: Record<string, unknown>) => ['digital-twins', 'admin', 'list', params ?? {}] as const,
        adminDetail: (id: string | number) => ['digital-twins', 'admin', 'detail', String(id)] as const,
        adminCategories: () => ['digital-twins', 'admin', 'categories'] as const,
        adminAuditEvents: (id: string | number) => ['digital-twins', 'admin', 'audit-events', String(id)] as const,
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
        companyApprovalsCompaniesHouse: (companyId: string | number) =>
            ['admin', 'company-approvals', 'companies-house', String(companyId)] as const,
            supplierApplications: (params?: Record<string, unknown>) =>
                ['admin', 'supplier-applications', params ?? {}] as const,
        supplierApplicationAuditLogs: (id: string | number, params?: Record<string, unknown>) =>
            ['admin', 'supplier-applications', String(id), 'audit-logs', params ?? {}] as const,
        analyticsOverview: () => ['admin', 'analytics', 'overview'] as const,
        aiUsageMetrics: () => ['admin', 'ai-usage', 'metrics'] as const,
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
        aiEvents: (filters?: Record<string, unknown>) => ['admin', 'ai-events', filters ?? {}] as const,
        aiModelMetrics: (filters?: Record<string, unknown>) => ['admin', 'ai-model-metrics', filters ?? {}] as const,
        aiTrainingJobs: (filters?: Record<string, unknown>) => ['admin', 'ai-training', 'jobs', filters ?? {}] as const,
        supplierScrapeJobs: () => ['admin', 'supplier-scrapes'] as const,
        scrapedSuppliers: (jobId: string | number) =>
            ['admin', 'supplier-scrapes', String(jobId), 'results'] as const,
    },
    me: {
        supplierStatus: () => ['me', 'supplier', 'status'] as const,
        profile: () => ['me', 'profile'] as const,
        companies: () => ['me', 'companies'] as const,
        supplierDocuments: () => ['me', 'supplier', 'documents'] as const,
        supplierApplications: () => ['me', 'supplier', 'applications'] as const,
    },
};

export type QueryKey = ReturnType<
    | (typeof queryKeys)['ai']['workflows']['list']
    | (typeof queryKeys)['ai']['workflows']['step']
    | (typeof queryKeys)['ai']['chat']['root']
    | (typeof queryKeys)['ai']['chat']['threads']
    | (typeof queryKeys)['ai']['chat']['thread']
    | (typeof queryKeys)['suppliers']['list']
    | (typeof queryKeys)['suppliers']['detail']
    | (typeof queryKeys)['rfps']['root']
    | (typeof queryKeys)['rfps']['list']
    | (typeof queryKeys)['rfps']['detail']
    | (typeof queryKeys)['rfps']['proposals']
    | (typeof queryKeys)['quotes']['root']
    | (typeof queryKeys)['quotes']['rfq']
    | (typeof queryKeys)['quotes']['list']
    | (typeof queryKeys)['quotes']['compare']
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
    | (typeof queryKeys)['orders']['supplierList']
    | (typeof queryKeys)['orders']['supplierDetail']
    | (typeof queryKeys)['orders']['buyerList']
    | (typeof queryKeys)['orders']['buyerDetail']
    | (typeof queryKeys)['downloads']['root']
    | (typeof queryKeys)['downloads']['list']
    | (typeof queryKeys)['analytics']['overview']
    | (typeof queryKeys)['analytics']['forecastReport']
    | (typeof queryKeys)['analytics']['supplierPerformanceReport']
    | (typeof queryKeys)['analytics']['supplierOptions']
    | (typeof queryKeys)['rfqs']['invitations']
    | (typeof queryKeys)['rfqs']['lines']
    | (typeof queryKeys)['rfqs']['suppliers']
    | (typeof queryKeys)['rfqs']['clarifications']
    | (typeof queryKeys)['rfqs']['timeline']
    | (typeof queryKeys)['rfqs']['attachments']
    | (typeof queryKeys)['money']['settings']
    | (typeof queryKeys)['money']['taxCodes']
    | (typeof queryKeys)['localization']['settings']
    | (typeof queryKeys)['localization']['convert']
    | (typeof queryKeys)['localization']['uoms']
    | (typeof queryKeys)['settings']['company']
    | (typeof queryKeys)['settings']['ai']
    | (typeof queryKeys)['settings']['localization']
    | (typeof queryKeys)['settings']['numbering']
    | (typeof queryKeys)['settings']['notificationPreferences']
    | (typeof queryKeys)['companyInvitations']['list']
    | (typeof queryKeys)['companyMembers']['list']
    | (typeof queryKeys)['companyRoleTemplates']['list']
    | (typeof queryKeys)['purchaseOrders']['root']
    | (typeof queryKeys)['purchaseOrders']['list']
    | (typeof queryKeys)['purchaseOrders']['detail']
    | (typeof queryKeys)['purchaseOrders']['changeOrders']
    | (typeof queryKeys)['purchaseOrders']['events']
    | (typeof queryKeys)['invoices']['list']
    | (typeof queryKeys)['invoices']['detail']
    | (typeof queryKeys)['invoices']['supplierList']
    | (typeof queryKeys)['invoices']['supplierDetail']
    | (typeof queryKeys)['companies']['detail']
    | (typeof queryKeys)['companies']['documents']
    | (typeof queryKeys)['receiving']['root']
    | (typeof queryKeys)['receiving']['list']
    | (typeof queryKeys)['receiving']['detail']
    | (typeof queryKeys)['matching']['candidates']
    | (typeof queryKeys)['notifications']['list']
    | (typeof queryKeys)['notifications']['badge']
    | (typeof queryKeys)['risk']['root']
    | (typeof queryKeys)['risk']['list']
    | (typeof queryKeys)['risk']['detail']
    | (typeof queryKeys)['events']['deliveries']
    | (typeof queryKeys)['credits']['root']
    | (typeof queryKeys)['credits']['list']
    | (typeof queryKeys)['credits']['detail']
    | (typeof queryKeys)['digitalTwins']['libraryRoot']
    | (typeof queryKeys)['digitalTwins']['libraryList']
    | (typeof queryKeys)['digitalTwins']['libraryDetail']
    | (typeof queryKeys)['digitalTwins']['categories']
    | (typeof queryKeys)['digitalTwins']['adminRoot']
    | (typeof queryKeys)['digitalTwins']['adminList']
    | (typeof queryKeys)['digitalTwins']['adminDetail']
    | (typeof queryKeys)['digitalTwins']['adminCategories']
    | (typeof queryKeys)['digitalTwins']['adminAuditEvents']
    | (typeof queryKeys)['inventory']['root']
    | (typeof queryKeys)['inventory']['items']
    | (typeof queryKeys)['inventory']['item']
    | (typeof queryKeys)['inventory']['locations']
    | (typeof queryKeys)['inventory']['movementsList']
    | (typeof queryKeys)['inventory']['movement']
    | (typeof queryKeys)['inventory']['lowStock']
    | (typeof queryKeys)['admin']['analyticsOverview']
    | (typeof queryKeys)['admin']['aiUsageMetrics']
    | (typeof queryKeys)['admin']['companies']
    | (typeof queryKeys)['admin']['companyApprovals']
    | (typeof queryKeys)['admin']['supplierApplications']
    | (typeof queryKeys)['admin']['supplierApplicationAuditLogs']
    | (typeof queryKeys)['admin']['plans']
    | (typeof queryKeys)['admin']['plan']
    | (typeof queryKeys)['admin']['roles']
    | (typeof queryKeys)['admin']['apiKeys']
    | (typeof queryKeys)['admin']['webhooks']
    | (typeof queryKeys)['admin']['webhookDeliveries']
    | (typeof queryKeys)['admin']['rateLimits']
    | (typeof queryKeys)['admin']['auditLog']
    | (typeof queryKeys)['admin']['aiEvents']
    | (typeof queryKeys)['admin']['aiModelMetrics']
    | (typeof queryKeys)['admin']['aiTrainingJobs']
    | (typeof queryKeys)['admin']['supplierScrapeJobs']
    | (typeof queryKeys)['admin']['scrapedSuppliers']
    | (typeof queryKeys)['me']['supplierStatus']
    | (typeof queryKeys)['me']['profile']
    | (typeof queryKeys)['me']['companies']
    | (typeof queryKeys)['me']['supplierDocuments']
    | (typeof queryKeys)['me']['supplierApplications']
>;
