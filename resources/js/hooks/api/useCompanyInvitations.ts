import {
    useMutation,
    useQuery,
    useQueryClient,
    type UseMutationOptions,
    type UseMutationResult,
    type UseQueryResult,
} from '@tanstack/react-query';

import { api, buildQuery, type ApiError } from '@/lib/api';
import { toCursorMeta, type CursorPaginationMeta } from '@/lib/pagination';
import { queryKeys } from '@/lib/queryKeys';
import type {
    CompanyInvitation,
    CompanyInvitationStatus,
    CompanyUserRole,
} from '@/types/company';

interface ApiCompanyInvitation {
    id: number;
    email: string;
    role: CompanyUserRole;
    status: CompanyInvitationStatus;
    company_id: number;
    invited_by?: number | null;
    expires_at?: string | null;
    accepted_at?: string | null;
    revoked_at?: string | null;
    message?: string | null;
    created_at?: string | null;
}

interface CompanyInvitationCollectionResponse {
    items: ApiCompanyInvitation[];
    meta?: Record<string, unknown> | null;
}

export interface CompanyInvitationCollection {
    items: CompanyInvitation[];
    meta?: CursorPaginationMeta;
}

export interface CompanyInvitationListParams {
    cursor?: string;
    perPage?: number;
}

export interface InvitationDraft {
    email: string;
    role: CompanyUserRole;
    expiresAt?: string | null;
    message?: string | null;
}

function mapInvitation(payload: ApiCompanyInvitation): CompanyInvitation {
    return {
        id: payload.id,
        email: payload.email,
        role: payload.role,
        status: payload.status,
        companyId: payload.company_id,
        invitedBy: payload.invited_by ?? null,
        expiresAt: payload.expires_at ?? null,
        acceptedAt: payload.accepted_at ?? null,
        revokedAt: payload.revoked_at ?? null,
        message: payload.message ?? null,
        createdAt: payload.created_at ?? null,
    };
}

function normalizeCollection(
    response: CompanyInvitationCollectionResponse,
): CompanyInvitationCollection {
    const items = Array.isArray(response.items)
        ? response.items.map(mapInvitation)
        : [];
    const meta = toCursorMeta(response.meta ?? undefined);
    return { items, meta };
}

export function useCompanyInvitations(
    params?: CompanyInvitationListParams,
    options?: { enabled?: boolean },
): UseQueryResult<CompanyInvitationCollection, ApiError> {
    const queryParams: Record<string, string | number> = {};

    if (typeof params?.cursor === 'string' && params.cursor.length > 0) {
        queryParams.cursor = params.cursor;
    }

    if (typeof params?.perPage === 'number') {
        queryParams.per_page = params.perPage;
    }

    return useQuery<CompanyInvitationCollection, ApiError>({
        queryKey: queryKeys.companyInvitations.list(queryParams),
        queryFn: async () => {
            const query = buildQuery(queryParams);
            const response =
                (await api.get<CompanyInvitationCollectionResponse>(
                    `/company-invitations${query}`,
                )) as unknown as CompanyInvitationCollectionResponse;

            return normalizeCollection(response);
        },
        staleTime: 15_000,
        enabled: options?.enabled ?? true,
    });
}

export function useSendCompanyInvitations(): UseMutationResult<
    CompanyInvitationCollection,
    ApiError,
    InvitationDraft[]
> {
    const queryClient = useQueryClient();

    return useMutation<
        CompanyInvitationCollection,
        ApiError,
        InvitationDraft[]
    >({
        mutationFn: async (drafts) => {
            const response =
                (await api.post<CompanyInvitationCollectionResponse>(
                    '/company-invitations',
                    {
                        invitations: drafts.map((draft) => ({
                            email: draft.email,
                            role: draft.role,
                            expires_at: draft.expiresAt ?? null,
                            message: draft.message ?? null,
                        })),
                    },
                )) as unknown as CompanyInvitationCollectionResponse;

            return normalizeCollection(response);
        },
        onSuccess: async (data) => {
            await queryClient.invalidateQueries({
                queryKey: ['company-invitations', 'list'],
            });
            queryClient.setQueryData(queryKeys.companyInvitations.list(), data);
        },
    });
}

export function useRevokeCompanyInvitation(): UseMutationResult<
    void,
    ApiError,
    number
> {
    const queryClient = useQueryClient();

    return useMutation<void, ApiError, number>({
        mutationFn: async (invitationId) => {
            await api.delete(`/company-invitations/${invitationId}`);
        },
        onSuccess: async () => {
            await queryClient.invalidateQueries({
                queryKey: ['company-invitations', 'list'],
            });
        },
    });
}

export function useAcceptCompanyInvitation(
    options?: UseMutationOptions<CompanyInvitation, ApiError, string>,
): UseMutationResult<CompanyInvitation, ApiError, string> {
    return useMutation<CompanyInvitation, ApiError, string>({
        mutationFn: async (token: string) => {
            const response = (await api.post<ApiCompanyInvitation>(
                `/company-invitations/${token}/accept`,
            )) as unknown as ApiCompanyInvitation;
            return mapInvitation(response);
        },
        ...options,
    });
}
