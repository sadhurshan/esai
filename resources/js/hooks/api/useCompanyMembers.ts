import {
    keepPreviousData,
    useMutation,
    useQuery,
    useQueryClient,
    type UseMutationResult,
    type UseQueryResult,
} from '@tanstack/react-query';

import { api, type ApiError, buildQuery } from '@/lib/api';
import { toCursorMeta } from '@/lib/pagination';
import { queryKeys } from '@/lib/queryKeys';
import type {
    CompanyMember,
    CompanyMemberCollection,
    CompanyMemberRoleConflict,
    CompanyUserRole,
} from '@/types/company';

interface ApiCompanyMember {
    id: number;
    name: string;
    email: string;
    role: CompanyUserRole;
    job_title?: string | null;
    phone?: string | null;
    avatar_url?: string | null;
    last_login_at?: string | null;
    is_active_company: boolean;
    membership?: {
        id?: number | null;
        company_id?: number | null;
        is_default?: boolean;
        last_used_at?: string | null;
        created_at?: string | null;
        updated_at?: string | null;
    };
    role_conflict?: {
        has_conflict?: boolean;
        buyer_supplier_conflict?: boolean;
        total_companies?: number;
        distinct_roles?: CompanyUserRole[];
    };
}

interface CompanyMemberCollectionResponse {
    items: ApiCompanyMember[];
    meta?: Record<string, unknown> | null;
}

export interface CompanyMemberListParams {
    cursor?: string;
    perPage?: number;
}

interface UpdateCompanyMemberPayload {
    memberId: number;
    role: CompanyUserRole;
}

function mapMember(payload: ApiCompanyMember): CompanyMember {
    return {
        id: payload.id,
        name: payload.name,
        email: payload.email,
        role: payload.role,
        jobTitle: payload.job_title ?? null,
        phone: payload.phone ?? null,
        avatarUrl: payload.avatar_url ?? null,
        lastLoginAt: payload.last_login_at ?? null,
        isActiveCompany: payload.is_active_company,
        membership: {
            id: payload.membership?.id ?? null,
            companyId: payload.membership?.company_id ?? null,
            isDefault: Boolean(payload.membership?.is_default),
            lastUsedAt: payload.membership?.last_used_at ?? null,
            createdAt: payload.membership?.created_at ?? null,
            updatedAt: payload.membership?.updated_at ?? null,
        },
        roleConflict: mapRoleConflict(payload.role_conflict, payload.role),
    };
}

function mapRoleConflict(conflictPayload: ApiCompanyMember['role_conflict'], fallbackRole: CompanyUserRole): CompanyMemberRoleConflict {
    const rolesSource = Array.isArray(conflictPayload?.distinct_roles) ? conflictPayload.distinct_roles : null;
    const distinctRoles = rolesSource?.filter((role): role is CompanyUserRole => Boolean(role)) ?? [];

    return {
        hasConflict: Boolean(conflictPayload?.has_conflict),
        buyerSupplierConflict: Boolean(conflictPayload?.buyer_supplier_conflict),
        totalCompanies: conflictPayload?.total_companies ?? 1,
        distinctRoles: distinctRoles.length > 0 ? distinctRoles : [fallbackRole],
    };
}

function normalizeCollection(response: CompanyMemberCollectionResponse): CompanyMemberCollection {
    const items = Array.isArray(response.items) ? response.items.map(mapMember) : [];
    const meta = toCursorMeta(response.meta ?? undefined);

    return {
        items,
        meta,
    };
}

export function useCompanyMembers(params?: CompanyMemberListParams): UseQueryResult<CompanyMemberCollection, ApiError> {
    const queryParams = {
        cursor: params?.cursor,
        per_page: params?.perPage,
    } satisfies Record<string, number | string | undefined>;

    return useQuery<CompanyMemberCollection, ApiError>({
        queryKey: queryKeys.companyMembers.list(queryParams),
        queryFn: async () => {
            const query = buildQuery(queryParams);
            const response = (await api.get<CompanyMemberCollectionResponse>(`/company-members${query}`)) as unknown as CompanyMemberCollectionResponse;
            return normalizeCollection(response);
        },
        placeholderData: keepPreviousData,
    });
}

export function useUpdateCompanyMember(): UseMutationResult<CompanyMember, ApiError, UpdateCompanyMemberPayload> {
    const queryClient = useQueryClient();

    return useMutation<CompanyMember, ApiError, UpdateCompanyMemberPayload>({
        mutationFn: async ({ memberId, role }) => {
            const response = (await api.patch<ApiCompanyMember>(`/company-members/${memberId}`, { role })) as unknown as ApiCompanyMember;
            return mapMember(response);
        },
        onSuccess: async () => {
            await queryClient.invalidateQueries({ queryKey: ['company-members', 'list'] });
        },
    });
}

export function useRemoveCompanyMember(): UseMutationResult<void, ApiError, number> {
    const queryClient = useQueryClient();

    return useMutation<void, ApiError, number>({
        mutationFn: async (memberId) => {
            await api.delete(`/company-members/${memberId}`);
        },
        onSuccess: async () => {
            await queryClient.invalidateQueries({ queryKey: ['company-members', 'list'] });
        },
    });
}
