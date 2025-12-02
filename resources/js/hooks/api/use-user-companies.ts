import { useMutation, useQuery, useQueryClient, type UseMutationResult, type UseQueryResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { UserCompanySummary } from '@/types/company';
import { useAuth } from '@/contexts/auth-context';

interface UserCompaniesResponseItem {
    id: number;
    name: string;
    status?: string | null;
    supplier_status?: string | null;
    role?: string | null;
    is_default: boolean;
    is_active: boolean;
}

interface UserCompaniesResponse {
    items: UserCompaniesResponseItem[];
}

interface SwitchCompanyResponse {
    company_id: number;
}

function mapCompany(item: UserCompaniesResponseItem): UserCompanySummary {
    return {
        id: item.id,
        name: item.name,
        status: item.status ?? null,
        supplierStatus: item.supplier_status ?? null,
        role: item.role ?? null,
        isDefault: Boolean(item.is_default),
        isActive: Boolean(item.is_active),
    };
}

export function useUserCompanies(): UseQueryResult<UserCompanySummary[], ApiError> {
    return useQuery<UserCompanySummary[], ApiError>({
        queryKey: queryKeys.me.companies(),
        queryFn: async () => {
            const response = (await api.get<UserCompaniesResponse>('/me/companies')) as unknown as UserCompaniesResponse | undefined;
            return response?.items?.map(mapCompany) ?? [];
        },
        staleTime: 60 * 1000,
    });
}

export function useSwitchCompany(): UseMutationResult<SwitchCompanyResponse, ApiError, number> {
    const queryClient = useQueryClient();
    const { refresh } = useAuth();

    return useMutation<SwitchCompanyResponse, ApiError, number>({
        mutationFn: async (companyId) => {
            const data = (await api.post<SwitchCompanyResponse>('/me/companies/switch', {
                company_id: companyId,
            })) as unknown as SwitchCompanyResponse;
            return data;
        },
        onSuccess: async () => {
            await Promise.all([
                queryClient.invalidateQueries({ queryKey: queryKeys.me.companies() }),
                queryClient.invalidateQueries({ queryKey: queryKeys.me.profile() }),
                refresh(),
            ]);
        },
    });
}
