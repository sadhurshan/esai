import type {
    RfpDetail,
    RfpProposalSummary,
    RfpProposalSummaryResponse,
} from '@/types/rfp';

const readString = (
    source: Record<string, unknown>,
    ...keys: string[]
): string | undefined => {
    for (const key of keys) {
        const value = source[key];
        if (typeof value === 'string' && value.length > 0) {
            return value;
        }
    }
    return undefined;
};

const readNumber = (
    source: Record<string, unknown>,
    ...keys: string[]
): number | undefined => {
    for (const key of keys) {
        const value = source[key];
        if (typeof value === 'number' && Number.isFinite(value)) {
            return value;
        }
        if (typeof value === 'string' && value.trim().length > 0) {
            const parsed = Number(value);
            if (!Number.isNaN(parsed)) {
                return parsed;
            }
        }
    }
    return undefined;
};

const readBoolean = (
    source: Record<string, unknown>,
    ...keys: string[]
): boolean | undefined => {
    for (const key of keys) {
        const value = source[key];
        if (typeof value === 'boolean') {
            return value;
        }
        if (typeof value === 'number') {
            return value !== 0;
        }
        if (typeof value === 'string') {
            if (value === 'true' || value === '1') {
                return true;
            }
            if (value === 'false' || value === '0') {
                return false;
            }
        }
    }
    return undefined;
};

const readRecord = (
    source: Record<string, unknown>,
    ...keys: string[]
): Record<string, unknown> | undefined => {
    for (const key of keys) {
        const value = source[key];
        if (value && typeof value === 'object' && !Array.isArray(value)) {
            return value as Record<string, unknown>;
        }
    }
    return undefined;
};

export function mapRfpDetail(payload: Record<string, unknown> = {}): RfpDetail {
    const source = payload ?? {};

    return {
        id: readNumber(source, 'id') ?? 0,
        companyId: readNumber(source, 'companyId', 'company_id') ?? 0,
        title: readString(source, 'title') ?? 'Project RFP',
        status: readString(source, 'status') ?? 'draft',
        problemObjectives:
            readString(source, 'problemObjectives', 'problem_objectives') ??
            null,
        scope: readString(source, 'scope') ?? null,
        timeline: readString(source, 'timeline') ?? null,
        evaluationCriteria:
            readString(source, 'evaluationCriteria', 'evaluation_criteria') ??
            null,
        proposalFormat:
            readString(source, 'proposalFormat', 'proposal_format') ?? null,
        aiAssistEnabled:
            readBoolean(source, 'aiAssistEnabled', 'ai_assist_enabled') ??
            false,
        aiSuggestions:
            readRecord(source, 'aiSuggestions', 'ai_suggestions') ?? null,
        publishedAt: readString(source, 'publishedAt', 'published_at') ?? null,
        inReviewAt: readString(source, 'inReviewAt', 'in_review_at') ?? null,
        awardedAt: readString(source, 'awardedAt', 'awarded_at') ?? null,
        closedAt: readString(source, 'closedAt', 'closed_at') ?? null,
        meta: readRecord(source, 'meta') ?? null,
        createdAt: readString(source, 'createdAt', 'created_at') ?? null,
        updatedAt: readString(source, 'updatedAt', 'updated_at') ?? null,
    };
}

const readArray = (
    source: Record<string, unknown>,
    key: string,
): unknown[] | undefined => {
    const value = source[key];
    if (Array.isArray(value)) {
        return value;
    }

    return undefined;
};

const readObject = (input: unknown): Record<string, unknown> | null => {
    if (input && typeof input === 'object' && !Array.isArray(input)) {
        return input as Record<string, unknown>;
    }

    return null;
};

export function mapRfpProposalSummary(
    payload: Record<string, unknown>,
): RfpProposalSummary {
    const source = payload ?? {};
    const supplierSource = readObject(
        source.supplierCompany ?? source.supplier_company,
    );

    return {
        id: readNumber(source, 'id') ?? 0,
        rfpId: readNumber(source, 'rfpId', 'rfp_id') ?? 0,
        companyId: readNumber(source, 'companyId', 'company_id') ?? 0,
        supplierCompanyId: readNumber(
            source,
            'supplierCompanyId',
            'supplier_company_id',
        ),
        supplierCompany: supplierSource
            ? {
                  id: readNumber(supplierSource, 'id'),
                  name: readString(supplierSource, 'name'),
              }
            : null,
        priceTotal: readNumber(source, 'priceTotal', 'price_total'),
        priceTotalMinor: readNumber(
            source,
            'priceTotalMinor',
            'price_total_minor',
        ),
        currency: readString(source, 'currency'),
        leadTimeDays: readNumber(source, 'leadTimeDays', 'lead_time_days'),
        status: readString(source, 'status'),
        attachmentsCount: readNumber(
            source,
            'attachmentsCount',
            'attachments_count',
        ),
        approachSummary: readString(
            source,
            'approachSummary',
            'approach_summary',
        ),
        scheduleSummary: readString(
            source,
            'scheduleSummary',
            'schedule_summary',
        ),
        valueAddSummary: readString(
            source,
            'valueAddSummary',
            'value_add_summary',
        ),
        meta: readRecord(source, 'meta') ?? null,
        submittedBy: readNumber(source, 'submittedBy', 'submitted_by'),
        createdAt: readString(source, 'createdAt', 'created_at') ?? null,
        updatedAt: readString(source, 'updatedAt', 'updated_at') ?? null,
    };
}

export function mapRfpProposalCollection(
    payload: Record<string, unknown>,
): RfpProposalSummaryResponse {
    const source = payload ?? {};
    const itemsSource = readArray(source, 'items') ?? [];
    const summarySource = readRecord(source, 'summary') ?? {};

    const items = itemsSource
        .map((item) =>
            item && typeof item === 'object'
                ? mapRfpProposalSummary(item as Record<string, unknown>)
                : null,
        )
        .filter((item): item is RfpProposalSummary => Boolean(item));

    return {
        items,
        summary: {
            total: readNumber(summarySource, 'total') ?? items.length,
            minPriceMinor: readNumber(
                summarySource,
                'min_price_minor',
                'minPriceMinor',
            ),
            maxPriceMinor: readNumber(
                summarySource,
                'max_price_minor',
                'maxPriceMinor',
            ),
            minLeadTimeDays: readNumber(
                summarySource,
                'min_lead_time_days',
                'minLeadTimeDays',
            ),
            maxLeadTimeDays: readNumber(
                summarySource,
                'max_lead_time_days',
                'maxLeadTimeDays',
            ),
            currency: readString(summarySource, 'currency'),
        },
    };
}
