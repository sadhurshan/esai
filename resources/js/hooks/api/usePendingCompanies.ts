import { keepPreviousData, useMutation, useQuery, useQueryClient, type UseMutationResult, type UseQueryResult } from '@tanstack/react-query';

import { api, buildQuery, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { Company } from '@/types/company';

import { mapCompany, type CompanyResponse } from './useCompany';

interface PendingCompaniesResponse {
    items: CompanyResponse[];
    meta: {
        total: number;
        per_page: number;
        current_page: number;
        last_page: number;
    };
}

export interface PendingCompaniesParams extends Record<string, unknown> {
    status?: string;
    page?: number;
    per_page?: number;
}

export interface PendingCompaniesResult {
    items: Company[];
    meta: PendingCompaniesResponse['meta'];
}

export function usePendingCompanies(
    params: PendingCompaniesParams,
): UseQueryResult<PendingCompaniesResult, ApiError> {
    return useQuery<PendingCompaniesResponse, ApiError, PendingCompaniesResult>({
        queryKey: queryKeys.admin.companies(params),
        queryFn: async () => {
            const query = buildQuery(params);
            return (await api.get<PendingCompaniesResponse>(
                `/admin/companies${query}`,
            )) as unknown as PendingCompaniesResponse;
        },
        select: (response) => ({
            items: response.items.map(mapCompany),
            meta: response.meta,
        }),
        placeholderData: keepPreviousData,
        staleTime: 15_000,
    });
}

export function useApproveCompany(): UseMutationResult<Company, ApiError, { companyId: number }> {
    const queryClient = useQueryClient();

    return useMutation<Company, ApiError, { companyId: number }>({
        mutationFn: async ({ companyId }) => {
            const response = (await api.post<CompanyResponse>(
                `/admin/companies/${companyId}/approve`,
            )) as unknown as CompanyResponse;

            return mapCompany(response);
        },
        onSuccess: (company) => {
            queryClient.invalidateQueries({
                queryKey: ['admin', 'companies'],
            });
            queryClient.invalidateQueries({
                queryKey: queryKeys.companies.detail(company.id),
            });
        },
    });
}

export function useRejectCompany(): UseMutationResult<
    Company,
    ApiError,
    { companyId: number; reason: string }
> {
    const queryClient = useQueryClient();

    return useMutation<Company, ApiError, { companyId: number; reason: string }>({
        mutationFn: async ({ companyId, reason }) => {
            const response = (await api.post<CompanyResponse>(
                `/admin/companies/${companyId}/reject`,
                { reason },
            )) as unknown as CompanyResponse;

            return mapCompany(response);
        },
        onSuccess: (company) => {
            queryClient.invalidateQueries({
                queryKey: ['admin', 'companies'],
            });
            queryClient.invalidateQueries({
                queryKey: queryKeys.companies.detail(company.id),
            });
        },
    });
}
