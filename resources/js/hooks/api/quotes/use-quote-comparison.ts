import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useApiClientContext } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import {
    HttpError,
    QuoteFromJSON,
    type Quote,
    type QuoteStatusEnum,
} from '@/sdk';
import type { QuoteComparisonRow } from '@/types/quotes';

interface QuoteComparisonEnvelope {
    data?: {
        items?: QuoteComparisonRowResponse[];
    };
}

interface QuoteComparisonRowResponse {
    quote_id: number | string;
    rfq_id: number | string;
    supplier?: Quote['supplier'];
    currency: string;
    total_price_minor?: number;
    lead_time_days?: number;
    status?: QuoteStatusEnum;
    attachments_count?: number;
    submitted_at?: string | null;
    scores?: QuoteComparisonScoresResponse;
    quote: unknown;
}

interface QuoteComparisonScoresResponse {
    price?: number;
    lead_time?: number;
    rating?: number; // legacy key
    risk?: number;
    fit?: number;
    composite?: number;
    rank?: number;
}

export function useQuoteComparison(
    rfqId?: string | number,
    options?: { enabled?: boolean },
): UseQueryResult<QuoteComparisonRow[], HttpError> {
    const { configuration } = useApiClientContext();
    const fetchApi = configuration.fetchApi ?? fetch;

    return useQuery<QuoteComparisonRow[], HttpError>({
        queryKey: queryKeys.quotes.compare(String(rfqId ?? '')),
        enabled: Boolean(rfqId) && (options?.enabled ?? true),
        queryFn: async () => {
            if (!rfqId) {
                throw new Error('rfqId is required to compare quotes');
            }

            const baseUrl = (configuration.basePath ?? '').replace(/\/$/, '');
            const url = `${baseUrl}/api/rfqs/${rfqId}/quotes/compare`;
            const response = await fetchApi(url, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                let body: unknown;
                try {
                    body = await response.clone().json();
                } catch {
                    body = await response.text();
                }
                throw new HttpError(response, body);
            }

            const payload = (await response.json()) as QuoteComparisonEnvelope;
            const rows = payload.data?.items ?? [];
            return rows.map(mapComparisonRow);
        },
        staleTime: 30_000,
    });
}

function mapComparisonRow(raw: QuoteComparisonRowResponse): QuoteComparisonRow {
    const quote = (QuoteFromJSON(raw.quote) ?? {}) as Quote;
    const quoteId = quote.id ?? String(raw.quote_id);
    const rfqId =
        typeof raw.rfq_id === 'string'
            ? Number.parseInt(raw.rfq_id, 10)
            : Number(raw.rfq_id ?? quote.rfqId ?? 0);

    return {
        quoteId: quoteId ?? String(raw.quote_id),
        rfqId: Number.isFinite(rfqId) ? rfqId : quote.rfqId,
        supplier: raw.supplier ?? quote.supplier,
        currency: raw.currency ?? quote.currency,
        totalPriceMinor: raw.total_price_minor ?? quote.totalMinor,
        leadTimeDays: raw.lead_time_days ?? quote.leadTimeDays,
        status: raw.status ?? quote.status,
        attachmentsCount:
            raw.attachments_count ?? quote.attachments?.length ?? 0,
        submittedAt: raw.submitted_at
            ? new Date(raw.submitted_at)
            : quote.submittedAt,
        scores: normalizeScores(raw.scores),
        quote,
    };
}

function normalizeScores(raw?: QuoteComparisonScoresResponse) {
    return {
        price: clampScore(raw?.price),
        leadTime: clampScore(raw?.lead_time),
        risk: clampScore(raw?.risk ?? raw?.rating),
        fit: clampScore(raw?.fit),
        composite: clampScore(raw?.composite),
        rank: Math.max(0, raw?.rank ?? 0),
    };
}

function clampScore(value?: number): number {
    if (typeof value !== 'number' || Number.isNaN(value)) {
        return 0;
    }

    if (value <= 0) {
        return 0;
    }

    const normalized = Math.min(value, 1);
    return Number(normalized.toFixed(4));
}
