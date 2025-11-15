import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import type { CreditNoteDetail } from '@/types/sourcing';
import { CreditApi, HttpError } from '@/sdk';

import { mapCreditNoteDetail } from './utils';

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === 'object' && value !== null;

export function useCreditNote(creditNoteId?: number | string | null): UseQueryResult<CreditNoteDetail, HttpError> {
    const creditApi = useSdkClient(CreditApi);
    const parsedId = creditNoteId !== null && creditNoteId !== undefined ? Number(creditNoteId) : NaN;
    const enabled = Number.isFinite(parsedId) && parsedId > 0;

    return useQuery<Record<string, unknown>, HttpError, CreditNoteDetail>({
        queryKey: queryKeys.credits.detail(String(parsedId || 'unknown')),
        enabled,
        queryFn: async () => (await creditApi.getCreditNote(parsedId)) as Record<string, unknown>,
        select: (payload) => mapCreditNoteDetail(isRecord(payload) ? payload : {}),
    });
}
