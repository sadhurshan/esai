import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, buildQuery, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { Paged, Supplier } from '@/types/sourcing';

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

type SupplierResponse = Paged<{
    id: number;
    company_id: number;
    name: string;
    status: 'pending' | 'approved' | 'rejected' | 'suspended';
    capabilities: Supplier['capabilities'] | null;
    rating_avg: string | number | null;
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
    lead_time_days?: number | null;
    moq?: number | null;
    verified_at?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
}>;

const coerceNumber = (value: unknown): number | null => {
    if (value === null || value === undefined) {
        return null;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : null;
};

const mapSupplier = (payload: SupplierResponse['items'][number]): Supplier => ({
    id: payload.id,
    companyId: payload.company_id,
    name: payload.name,
    status: payload.status,
    capabilities: payload.capabilities ?? {},
    ratingAvg: Number(payload.rating_avg ?? 0),
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
});

type SupplierResult = { items: Supplier[]; meta: SupplierResponse['meta'] };

export function useSuppliers(params: SupplierQueryParams = {}): UseQueryResult<SupplierResult, ApiError> {
    return useQuery<SupplierResult, ApiError, SupplierResult>({
        queryKey: queryKeys.suppliers.list(params),
        queryFn: async () => {
            const query = buildQuery(params);
            const response = (await api.get<SupplierResponse>(`/suppliers${query}`)) as unknown as SupplierResponse;

            return {
                items: response.items.map(mapSupplier),
                meta: response.meta,
            };
        },
        staleTime: 30_000,
        placeholderData: keepPreviousData,
    });
}
