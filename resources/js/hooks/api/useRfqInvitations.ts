import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, buildQuery, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { RfqInvitation } from '@/types/sourcing';

interface InvitationResponseItem {
    id: number;
    status: 'pending' | 'accepted' | 'declined';
    invited_at: string | null;
    supplier: {
        id: number;
        name: string;
        rating_avg: number | null;
    } | null;
}

interface InvitationResponse {
    items: InvitationResponseItem[];
    meta: {
        total: number;
        per_page: number;
        current_page: number;
        last_page: number;
    };
}

const mapInvitation = (payload: InvitationResponseItem): RfqInvitation => ({
    id: payload.id,
    status: payload.status,
    invitedAt: payload.invited_at,
    supplier: payload.supplier
        ? {
              id: payload.supplier.id,
              name: payload.supplier.name,
              ratingAvg: payload.supplier.rating_avg ?? undefined,
          }
        : null,
});

export interface UseRfqInvitationsParams extends Record<string, unknown> {
    page?: number;
    per_page?: number;
}

interface UseRfqInvitationsResult {
    items: RfqInvitation[];
    meta: InvitationResponse['meta'];
}

export function useRfqInvitations(
    rfqId: number,
    params: UseRfqInvitationsParams = {},
): UseQueryResult<UseRfqInvitationsResult, ApiError> {
    return useQuery<InvitationResponse, ApiError, UseRfqInvitationsResult>({
        queryKey: [...queryKeys.rfqs.invitations(rfqId), params],
        enabled: Number.isFinite(rfqId) && rfqId > 0,
        queryFn: async () => {
            const query = buildQuery(params);
            return (await api.get<InvitationResponse>(
                `/rfqs/${rfqId}/invitations${query}`,
            )) as unknown as InvitationResponse;
        },
        select: (response) => ({
            items: response.items.map(mapInvitation),
            meta: response.meta,
        }),
        staleTime: 30_000,
    });
}
