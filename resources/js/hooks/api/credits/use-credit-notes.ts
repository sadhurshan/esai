import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import type { CreditNoteSummary } from '@/types/sourcing';
import { CreditApi, HttpError, type ListCreditNotesQuery } from '@/sdk';

import { mapCreditNoteSummary } from './utils';

interface CreditNoteCollectionResponse {
    items?: Record<string, unknown>[];
    data?: Record<string, unknown>[];
    meta?: Record<string, unknown> | null;
}

export interface CreditNoteListResult {
    items: CreditNoteSummary[];
    meta?: Record<string, unknown> | null;
}

const DEFAULT_PER_PAGE = 25;

type UseCreditNotesParams = ListCreditNotesQuery;

export function useCreditNotes(
    params: UseCreditNotesParams = {},
): UseQueryResult<CreditNoteListResult, HttpError> {
    const creditApi = useSdkClient(CreditApi);

    const { page = 1, perPage = DEFAULT_PER_PAGE, status, supplierId, invoiceId, createdFrom, createdTo, search } = params;

    return useQuery<CreditNoteCollectionResponse, HttpError, CreditNoteListResult>({
        queryKey: queryKeys.credits.list({
            page,
            perPage,
            status,
            supplierId,
            invoiceId,
            createdFrom,
            createdTo,
            search,
        }),
        placeholderData: keepPreviousData,
        queryFn: async () =>
            (await creditApi.listCreditNotes({
                page,
                perPage,
                status,
                supplierId,
                invoiceId,
                createdFrom,
                createdTo,
                search,
            })) as CreditNoteCollectionResponse,
        select: (response) => {
            const rawItems = (response?.items ?? response?.data ?? []) as unknown[];
            const items = rawItems
                .filter((item): item is Record<string, unknown> => typeof item === 'object' && item !== null)
                .map((item) => mapCreditNoteSummary(item));

            return {
                items,
                meta: response?.meta ?? null,
            };
        },
        staleTime: 15_000,
        gcTime: 60_000,
    });
}
