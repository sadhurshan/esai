import { useMutation, useQuery, useQueryClient, type UseMutationResult, type UseQueryResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { SupplierDocument } from './useSupplierDocuments';

export type DirectoryVisibility = 'private' | 'public';

export type SupplierApplicationStatusValue = 'pending' | 'approved' | 'rejected';

export interface SupplierApplicationSummary {
    id: number;
    status: SupplierApplicationStatusValue;
    notes?: string | null;
    submitted_at?: string | null;
    auto_reverification?: boolean;
    documents?: SupplierDocument[];
}

export interface SupplierSelfStatus {
    supplier_status: string;
    directory_visibility: DirectoryVisibility;
    supplier_profile_completed_at: string | null;
    is_listed: boolean;
    current_application?: SupplierApplicationSummary | null;
}

export interface UpdateVisibilityInput {
    visibility: DirectoryVisibility;
}

export interface SupplierCapabilitiesPayload {
    methods?: string[];
    materials?: string[];
    tolerances?: string[];
    finishes?: string[];
    industries?: string[];
}

export interface SupplierContactPayload {
    name?: string;
    email?: string;
    phone?: string;
}

export interface SupplierApplicationPayload {
    description?: string;
    capabilities: SupplierCapabilitiesPayload;
    address?: string;
    city?: string;
    country?: string;
    moq?: number;
    min_order_qty?: number;
    lead_time_days?: number;
    certifications?: string[];
    facilities?: string;
    website?: string;
    contact?: SupplierContactPayload;
    notes?: string;
    documents?: number[];
}

export interface SupplierApplicationResponse {
    id: number;
    status: string;
    company_id: number;
    submitted_by?: number;
    created_at?: string;
}

export function useSupplierSelfStatus(initialData?: SupplierSelfStatus | null): UseQueryResult<SupplierSelfStatus, ApiError> {
    return useQuery<SupplierSelfStatus, ApiError, SupplierSelfStatus>({
        queryKey: queryKeys.me.supplierStatus(),
        queryFn: async () =>
            // TODO: switch to canonical /me/supplier-application/status once backend endpoint is available.
            (await api.get<SupplierSelfStatus>('/me/supplier-application/status')) as unknown as SupplierSelfStatus,
        initialData: initialData ?? undefined,
        staleTime: 15_000,
    });
}

export function useUpdateSupplierVisibility(): UseMutationResult<SupplierSelfStatus, ApiError, UpdateVisibilityInput> {
    const queryClient = useQueryClient();

    return useMutation<SupplierSelfStatus, ApiError, UpdateVisibilityInput>({
        mutationFn: async (payload) =>
            (await api.put<SupplierSelfStatus>('/me/supplier/visibility', payload)) as unknown as SupplierSelfStatus,
        onSuccess: (status) => {
            queryClient.setQueryData(queryKeys.me.supplierStatus(), status);
        },
    });
}

export function useApplyForSupplier(): UseMutationResult<
    SupplierApplicationResponse,
    ApiError,
    SupplierApplicationPayload
> {
    return useMutation<SupplierApplicationResponse, ApiError, SupplierApplicationPayload>({
        mutationFn: async (payload) =>
            (await api.post<SupplierApplicationResponse>('/me/apply-supplier', payload)) as unknown as SupplierApplicationResponse,
    });
}
