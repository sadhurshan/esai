import { useMemo } from 'react';
import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { PageMeta, RfqInvitation } from '@/sdk';

type RfqIdentifier = string | number | null | undefined;

export interface InvitationSupplierProfile {
    id?: string | number | null;
    name?: string | null;
    city?: string | null;
    country?: string | null;
    ratingAvg?: number | null;
    capabilities?: { methods?: string[] | null } | null;
}

export type RfqInvitationWithProfile = RfqInvitation & {
    supplier?: InvitationSupplierProfile | null;
};

export interface UseRfqSuppliersOptions {
    enabled?: boolean;
}

interface ApiInvitationSupplier {
    id?: string | number | null;
    name?: string | null;
    city?: string | null;
    country?: string | null;
    rating_avg?: number | null;
    ratingAvg?: number | null;
    capabilities?: { methods?: string[] | null } | null;
}

interface ApiRfqInvitation {
    id?: string | number | null;
    supplier_id?: string | number | null;
    status?: string | null;
    invited_at?: string | null;
    responded_at?: string | null;
    supplier?: ApiInvitationSupplier | null;
}

interface RfqSuppliersApiResponse {
    items?: ApiRfqInvitation[];
    meta?: PageMeta;
}

interface RfqSuppliersPayload {
    items: RfqInvitationWithProfile[];
    meta?: PageMeta;
}

const DEFAULT_INVITATION_STATUS = 'pending';

function parseDate(value?: string | null): Date | undefined {
    if (!value) {
        return undefined;
    }

    const parsed = new Date(value);
    return Number.isNaN(parsed.valueOf()) ? undefined : parsed;
}

function normalizeSupplierProfile(payload?: ApiInvitationSupplier | null): InvitationSupplierProfile | null {
    if (!payload) {
        return null;
    }

    return {
        id: payload.id ?? null,
        name: payload.name ?? null,
        city: payload.city ?? null,
        country: payload.country ?? null,
        ratingAvg: payload.ratingAvg ?? payload.rating_avg ?? null,
        capabilities: payload.capabilities ?? null,
    } satisfies InvitationSupplierProfile;
}

function normalizeInvitation(record: ApiRfqInvitation): RfqInvitationWithProfile {
    const invitation: RfqInvitationWithProfile = {
        id: String(record.id ?? ''),
        supplierId: String(record.supplier_id ?? ''),
        status: record.status ?? DEFAULT_INVITATION_STATUS,
        supplier: normalizeSupplierProfile(record.supplier),
    };

    const invitedAt = parseDate(record.invited_at);
    if (invitedAt) {
        invitation.invitedAt = invitedAt;
    }

    const respondedAt = parseDate(record.responded_at);
    if (respondedAt) {
        invitation.respondedAt = respondedAt;
    }

    return invitation;
}

async function fetchRfqSuppliers(rfqId: string | number): Promise<RfqSuppliersPayload> {
    const response = (await api.get<RfqSuppliersApiResponse>(`/rfqs/${rfqId}/invitations`)) as unknown as RfqSuppliersApiResponse;
    const items = Array.isArray(response.items) ? response.items : [];
    

    return {
        items: items.map(normalizeInvitation),
        meta: response.meta,
    } satisfies RfqSuppliersPayload;
}

export type UseRfqSuppliersResult = UseQueryResult<RfqSuppliersPayload, ApiError> & {
    items: RfqInvitationWithProfile[];
    meta?: PageMeta;
};

export function useRfqSuppliers(rfqId: RfqIdentifier, options: UseRfqSuppliersOptions = {}): UseRfqSuppliersResult {
    const enabled = options.enabled ?? Boolean(rfqId);

    const query = useQuery<RfqSuppliersPayload, ApiError>({
        queryKey: queryKeys.rfqs.suppliers(rfqId ?? 'undefined'),
        enabled,
        queryFn: () => fetchRfqSuppliers(rfqId as string | number),
    });

    const data = useMemo<RfqSuppliersPayload>(() => {
        if (!enabled) {
            return { items: [], meta: undefined };
        }

        return query.data ?? { items: [], meta: undefined };
    }, [enabled, query.data]);
    

    return {
        ...query,
        data,
        items: data.items,
        meta: data.meta,
    } as UseRfqSuppliersResult;
}
