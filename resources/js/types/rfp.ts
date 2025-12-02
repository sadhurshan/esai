export interface RfpDetail {
    id: number;
    companyId: number;
    title: string;
    status: string;
    problemObjectives?: string | null;
    scope?: string | null;
    timeline?: string | null;
    evaluationCriteria?: string | null;
    proposalFormat?: string | null;
    aiAssistEnabled: boolean;
    aiSuggestions?: Record<string, unknown> | null;
    publishedAt?: string | null;
    inReviewAt?: string | null;
    awardedAt?: string | null;
    closedAt?: string | null;
    meta?: Record<string, unknown> | null;
    createdAt?: string | null;
    updatedAt?: string | null;
}

export interface RfpProposalSummary {
    id: number;
    rfpId: number;
    companyId: number;
    supplierCompanyId?: number | null;
    supplierCompany?: {
        id?: number | null;
        name?: string | null;
    } | null;
    priceTotal?: number | null;
    priceTotalMinor?: number | null;
    currency?: string | null;
    leadTimeDays?: number | null;
    status?: string | null;
    attachmentsCount?: number | null;
    approachSummary?: string | null;
    scheduleSummary?: string | null;
    valueAddSummary?: string | null;
    meta?: Record<string, unknown> | null;
    submittedBy?: number | null;
    createdAt?: string | null;
    updatedAt?: string | null;
}

export interface RfpProposalSummaryResponse {
    items: RfpProposalSummary[];
    summary: {
        total: number;
        minPriceMinor?: number | null;
        maxPriceMinor?: number | null;
        minLeadTimeDays?: number | null;
        maxLeadTimeDays?: number | null;
        currency?: string | null;
    };
}
