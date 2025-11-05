import { useMutation, useQuery, useQueryClient, type UseMutationResult, type UseQueryResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';

export type DirectoryVisibility = 'private' | 'public';

export interface SupplierSelfStatus {
    supplier_status: string;
    directory_visibility: DirectoryVisibility;
    supplier_profile_completed_at: string | null;
    is_listed: boolean;
}

export interface UpdateVisibilityInput {
    visibility: DirectoryVisibility;
}

export function useSupplierSelfStatus(initialData?: SupplierSelfStatus | null): UseQueryResult<SupplierSelfStatus, ApiError> {
    return useQuery<SupplierSelfStatus, ApiError, SupplierSelfStatus>({
        queryKey: queryKeys.me.supplierStatus(),
        queryFn: async () =>
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
