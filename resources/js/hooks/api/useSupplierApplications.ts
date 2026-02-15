import {
    useMutation,
    useQuery,
    useQueryClient,
    type UseMutationResult,
    type UseQueryResult,
} from '@tanstack/react-query';

import type { SupplierDocument } from '@/hooks/api/useSupplierDocuments';
import type { SupplierApplicationStatusValue } from '@/hooks/api/useSupplierSelfService';
import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';

export interface SupplierApplicationRecord {
    id: number;
    company_id: number;
    status: SupplierApplicationStatusValue;
    submitted_by?: number | null;
    reviewed_by?: number | null;
    reviewed_at?: string | null;
    notes?: string | null;
    form_json?: Record<string, unknown> | null;
    created_at?: string | null;
    updated_at?: string | null;
    documents?: SupplierDocument[];
}

export interface SupplierApplicationCollection {
    items: SupplierApplicationRecord[];
}

interface UseSupplierApplicationsOptions {
    enabled?: boolean;
}

export function useSupplierApplications(
    options?: UseSupplierApplicationsOptions,
): UseQueryResult<SupplierApplicationCollection, ApiError> {
    return useQuery<SupplierApplicationCollection, ApiError>({
        queryKey: queryKeys.me.supplierApplications(),
        queryFn: async () =>
            (await api.get<SupplierApplicationCollection>(
                '/supplier-applications',
            )) as unknown as SupplierApplicationCollection,
        staleTime: 15_000,
        enabled: options?.enabled ?? true,
    });
}

export function useWithdrawSupplierApplication(): UseMutationResult<
    void,
    ApiError,
    number
> {
    const queryClient = useQueryClient();

    return useMutation<void, ApiError, number>({
        mutationFn: async (applicationId) => {
            await api.delete(`/supplier-applications/${applicationId}`);
        },
        onSuccess: async () => {
            await Promise.allSettled([
                queryClient.invalidateQueries({
                    queryKey: queryKeys.me.supplierApplications(),
                }),
                queryClient.invalidateQueries({
                    queryKey: queryKeys.me.supplierStatus(),
                }),
            ]);
        },
    });
}
