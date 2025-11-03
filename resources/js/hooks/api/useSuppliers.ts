import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, buildQuery, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { Paged, Supplier } from '@/types/sourcing';

export interface SupplierQueryParams extends Record<string, unknown> {
    q?: string;
    method?: string;
    material?: string;
    region?: string;
    sort?: 'rating' | 'avg_response_hours';
    page?: number;
    per_page?: number;
}

type SupplierResponse = Paged<{
    id: number;
    name: string;
    rating: number;
    capabilities: string[];
    materials: string[];
    location_region: string;
    min_order_qty: number;
    avg_response_hours: number;
}>;

const mapSupplier = (payload: SupplierResponse['items'][number]): Supplier => ({
    id: payload.id,
    name: payload.name,
    rating: payload.rating,
    capabilities: payload.capabilities ?? [],
    materials: payload.materials ?? [],
    locationRegion: payload.location_region ?? '',
    minimumOrderQuantity: payload.min_order_qty ?? 0,
    averageResponseHours: payload.avg_response_hours ?? 0,
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
