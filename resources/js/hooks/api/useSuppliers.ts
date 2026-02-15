import {
    keepPreviousData,
    useQuery,
    type UseQueryResult,
} from '@tanstack/react-query';

import { api, buildQuery, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type {
    CostBandEstimate,
    Paged,
    Supplier,
    SupplierDocument,
    SupplierDocumentType,
} from '@/types/sourcing';

export interface SupplierQueryParams extends Record<string, unknown> {
    q?: string;
    capability?: string;
    material?: string;
    finish?: string;
    tolerance?: string;
    location?: string;
    industry?: string;
    cert?: string;
    rating_min?: number;
    lead_time_max?: number;
    sort?: 'match_score' | 'rating' | 'lead_time' | 'distance' | 'price_band';
    page?: number;
    per_page?: number;
}

export interface SupplierDocumentResponse {
    id: number;
    supplier_id: number;
    company_id: number;
    type: SupplierDocumentType;
    status: 'valid' | 'expiring' | 'expired';
    path: string;
    mime: string;
    size_bytes: number;
    issued_at?: string | null;
    expires_at?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
}

export interface SupplierApiPayload {
    id: number;
    company_id: number;
    name: string;
    status: 'pending' | 'approved' | 'rejected' | 'suspended';
    capabilities: Supplier['capabilities'] | null;
    rating_avg: string | number | null;
    risk_grade?: string | null;
    contact?: {
        email?: string | null;
        phone?: string | null;
        website?: string | null;
    } | null;
    address?: {
        line1?: string | null;
        city?: string | null;
        country?: string | null;
    } | null;
    geo?: {
        lat?: string | number | null;
        lng?: string | number | null;
    } | null;
    branding?: {
        logo_url?: string | null;
        mark_url?: string | null;
    } | null;
    company?: {
        id: number;
        name: string;
        website?: string | null;
        country?: string | null;
        supplier_status?: string | null;
        is_verified?: boolean | null;
    } | null;
    certificates?: {
        valid?: number;
        expiring?: number;
        expired?: number;
    } | null;
    documents?: SupplierDocumentResponse[] | null;
    lead_time_days?: number | null;
    moq?: number | null;
    verified_at?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
}

type SupplierResponse = Paged<SupplierApiPayload>;

type CostBandEstimatePayload = {
    status: 'estimated' | 'insufficient_data';
    min_minor?: number | null;
    max_minor?: number | null;
    currency?: string | null;
    sample_size: number;
    period_months: number;
    matched_on: {
        process?: string | null;
        material?: string | null;
        finish?: string | null;
        region?: string | null;
    };
    explanation: string;
};

const coerceNumber = (value: unknown): number | null => {
    if (value === null || value === undefined) {
        return null;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : null;
};

const mapSupplierDocument = (
    payload: SupplierDocumentResponse,
): SupplierDocument => ({
    id: payload.id,
    supplierId: payload.supplier_id,
    companyId: payload.company_id,
    type: payload.type,
    status: payload.status,
    path: payload.path,
    mime: payload.mime,
    sizeBytes: payload.size_bytes,
    issuedAt: payload.issued_at ?? null,
    expiresAt: payload.expires_at ?? null,
    createdAt: payload.created_at ?? null,
    updatedAt: payload.updated_at ?? null,
});

export const mapSupplier = (payload: SupplierApiPayload): Supplier => ({
    id: payload.id,
    companyId: payload.company_id,
    name: payload.name,
    status: payload.status,
    capabilities: payload.capabilities ?? {},
    ratingAvg: Number(payload.rating_avg ?? 0),
    riskGrade: payload.risk_grade ?? null,
    contact: {
        email: payload.contact?.email ?? null,
        phone: payload.contact?.phone ?? null,
        website: payload.contact?.website ?? null,
    },
    address: {
        line1: payload.address?.line1 ?? null,
        city: payload.address?.city ?? null,
        country: payload.address?.country ?? null,
    },
    geo: {
        lat: coerceNumber(payload.geo?.lat),
        lng: coerceNumber(payload.geo?.lng),
    },
    leadTimeDays: payload.lead_time_days ?? null,
    moq: payload.moq ?? null,
    verifiedAt: payload.verified_at ?? null,
    createdAt: payload.created_at ?? null,
    updatedAt: payload.updated_at ?? null,
    branding: {
        logoUrl: payload.branding?.logo_url ?? null,
        markUrl: payload.branding?.mark_url ?? null,
    },
    certificates: {
        valid: payload.certificates?.valid ?? 0,
        expiring: payload.certificates?.expiring ?? 0,
        expired: payload.certificates?.expired ?? 0,
    },
    company: payload.company
        ? {
              id: payload.company.id,
              name: payload.company.name,
              website: payload.company.website ?? null,
              country: payload.company.country ?? null,
              supplierStatus: payload.company.supplier_status ?? null,
              isVerified: payload.company.is_verified ?? null,
          }
        : null,
    documents: payload.documents
        ? payload.documents.map(mapSupplierDocument)
        : null,
});

type SupplierResult = { items: Supplier[]; meta: SupplierResponse['meta'] };

export function useSuppliers(
    params: SupplierQueryParams = {},
): UseQueryResult<SupplierResult, ApiError> {
    return useQuery<SupplierResult, ApiError, SupplierResult>({
        queryKey: queryKeys.suppliers.list(params),
        queryFn: async () => {
            const query = buildQuery(params);
            const response = (await api.get<SupplierResponse>(
                `/suppliers${query}`,
            )) as unknown as SupplierResponse;
            const metaWithEstimate = response.meta as unknown as {
                cost_band_estimate?: CostBandEstimatePayload | null;
                [key: string]: unknown;
            };
            const { cost_band_estimate: estimatePayload, ...restMeta } =
                metaWithEstimate ?? {};
            const costBandEstimate: CostBandEstimate | null = estimatePayload
                ? {
                      status: estimatePayload.status,
                      minMinor: estimatePayload.min_minor ?? null,
                      maxMinor: estimatePayload.max_minor ?? null,
                      currency: estimatePayload.currency ?? null,
                      sampleSize: estimatePayload.sample_size,
                      periodMonths: estimatePayload.period_months,
                      matchedOn: estimatePayload.matched_on ?? {},
                      explanation: estimatePayload.explanation,
                  }
                : null;

            return {
                items: response.items.map(mapSupplier),
                meta: {
                    ...(restMeta as SupplierResponse['meta']),
                    costBandEstimate,
                },
            };
        },
        staleTime: 30_000,
        placeholderData: keepPreviousData,
    });
}
