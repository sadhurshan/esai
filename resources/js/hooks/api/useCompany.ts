import {
    useMutation,
    useQuery,
    useQueryClient,
    type UseMutationResult,
    type UseQueryResult,
} from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { Company, CompanyStatus } from '@/types/company';

export interface CompanyResponse {
    id: number;
    name: string;
    slug: string;
    status: string;
    registration_no: string;
    tax_id: string;
    country: string;
    email_domain: string;
    primary_contact_name: string;
    primary_contact_email: string;
    primary_contact_phone: string;
    address: string | null;
    phone: string | null;
    website: string | null;
    region: string | null;
    rejection_reason: string | null;
    owner_user_id: number | null;
    created_at: string | null;
    updated_at: string | null;
    has_completed_onboarding: boolean;
}

export const mapCompany = (payload: CompanyResponse): Company => ({
    id: payload.id,
    name: payload.name,
    slug: payload.slug,
    status: payload.status as CompanyStatus,
    registrationNo: payload.registration_no,
    taxId: payload.tax_id,
    country: payload.country,
    emailDomain: payload.email_domain,
    primaryContactName: payload.primary_contact_name,
    primaryContactEmail: payload.primary_contact_email,
    primaryContactPhone: payload.primary_contact_phone,
    address: payload.address,
    phone: payload.phone,
    website: payload.website,
    region: payload.region,
    rejectionReason: payload.rejection_reason,
    ownerUserId: payload.owner_user_id ?? undefined,
    createdAt: payload.created_at,
    updatedAt: payload.updated_at,
    hasCompletedOnboarding: payload.has_completed_onboarding ?? false,
});

export interface RegisterCompanyInput {
    name: string;
    registration_no: string;
    tax_id: string;
    country: string;
    email_domain: string;
    primary_contact_name: string;
    primary_contact_email: string;
    primary_contact_phone: string;
    address?: string;
    phone?: string;
    website?: string;
    region?: string;
}

export type UpdateCompanyInput = Partial<RegisterCompanyInput>;

export function useCompany(
    companyId?: number | null,
): UseQueryResult<Company, ApiError> {
    return useQuery<CompanyResponse, ApiError, Company>({
        queryKey: queryKeys.companies.detail(companyId ?? 0),
        enabled: Boolean(companyId) && (companyId ?? 0) > 0,
        queryFn: async () =>
            (await api.get<CompanyResponse>(
                `/companies/${companyId}`,
            )) as unknown as CompanyResponse,
        select: mapCompany,
        staleTime: 30_000,
    });
}

export function useRegisterCompany(): UseMutationResult<
    Company,
    ApiError,
    RegisterCompanyInput
> {
    const queryClient = useQueryClient();

    return useMutation<Company, ApiError, RegisterCompanyInput>({
        mutationFn: async (payload) => {
            const response = (await api.post<CompanyResponse>(
                '/companies',
                payload,
            )) as unknown as CompanyResponse;

            return mapCompany(response);
        },
        onSuccess: (company) => {
            queryClient.setQueryData(
                queryKeys.companies.detail(company.id),
                company,
            );
        },
    });
}

export function useUpdateCompany(
    companyId: number,
): UseMutationResult<Company, ApiError, UpdateCompanyInput> {
    const queryClient = useQueryClient();

    return useMutation<Company, ApiError, UpdateCompanyInput>({
        mutationFn: async (payload) => {
            const response = (await api.put<CompanyResponse>(
                `/companies/${companyId}`,
                payload,
            )) as unknown as CompanyResponse;

            return mapCompany(response);
        },
        onSuccess: (company) => {
            queryClient.setQueryData(
                queryKeys.companies.detail(company.id),
                company,
            );
        },
    });
}
