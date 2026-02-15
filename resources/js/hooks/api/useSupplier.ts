import { useQuery } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { Supplier } from '@/types/sourcing';
import type { SupplierApiPayload } from './useSuppliers';
import { mapSupplier } from './useSuppliers';

export function useSupplier(id?: number) {
    return useQuery<SupplierApiPayload, ApiError, Supplier>({
        queryKey: queryKeys.suppliers.detail(id ?? 'unknown'),
        enabled: typeof id === 'number' && id > 0,
        queryFn: async () => {
            if (!id || id <= 0) {
                throw new Error('Supplier id is required');
            }

            return (await api.get<SupplierApiPayload>(
                `/suppliers/${id}`,
            )) as unknown as SupplierApiPayload;
        },
        select: (payload) => mapSupplier(payload),
        staleTime: 30_000,
    });
}
