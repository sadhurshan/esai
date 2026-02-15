import {
    useMutation,
    useQueryClient,
    type QueryClient,
} from '@tanstack/react-query';

import { useApiClientContext } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { HttpError, QuoteFromJSON, type Quote } from '@/sdk';
import type { QuoteComparisonRow } from '@/types/quotes';
import type { UseQuoteResult } from './use-quote';
import type { UseQuotesResult } from './use-quotes';

interface QuoteShortlistEnvelope {
    data?: {
        quote?: unknown;
    };
}

export interface QuoteShortlistMutationInput {
    quoteId: string | number;
    shortlist: boolean;
    rfqId?: string | number | null;
}

interface ShortlistMutationContext {
    detail?: {
        key: ReturnType<typeof queryKeys.quotes.detail>;
        snapshot?: UseQuoteResult;
    };
    lists?: Array<{ key: readonly unknown[]; snapshot?: UseQuotesResult }>;
    compare?: {
        key: ReturnType<typeof queryKeys.quotes.compare>;
        snapshot?: QuoteComparisonRow[];
    };
}

export function useQuoteShortlistMutation() {
    const { configuration } = useApiClientContext();
    const fetchApi = configuration.fetchApi ?? fetch;
    const queryClient = useQueryClient();

    return useMutation<
        Quote,
        HttpError | Error,
        QuoteShortlistMutationInput,
        ShortlistMutationContext
    >({
        mutationFn: async ({ quoteId, shortlist }) => {
            const baseUrl = (configuration.basePath ?? '').replace(/\/$/, '');
            const url = `${baseUrl}/api/quotes/${quoteId}/shortlist`;
            const response = await fetchApi(url, {
                method: shortlist ? 'POST' : 'DELETE',
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                let body: unknown;
                try {
                    body = await response.clone().json();
                } catch {
                    body = await response.text();
                }
                throw new HttpError(response, body);
            }

            const payload = (await response.json()) as QuoteShortlistEnvelope;
            const normalizedQuote = payload.data?.quote
                ? normalizeQuotePayload(payload.data.quote)
                : undefined;
            const parsed = normalizedQuote
                ? QuoteFromJSON(normalizedQuote)
                : undefined;

            if (!parsed) {
                throw new Error(
                    'Shortlist response did not contain quote details.',
                );
            }

            return parsed;
        },
        onMutate: async ({ quoteId, shortlist, rfqId }) => {
            const context: ShortlistMutationContext = {};
            const detailKey = queryKeys.quotes.detail(String(quoteId));
            context.detail = {
                key: detailKey,
                snapshot: queryClient.getQueryData<UseQuoteResult>(detailKey),
            };

            if (context.detail.snapshot?.quote) {
                queryClient.setQueryData<UseQuoteResult>(detailKey, {
                    ...context.detail.snapshot,
                    quote: {
                        ...context.detail.snapshot.quote,
                        isShortlisted: shortlist,
                    },
                });
            }

            if (rfqId != null) {
                const compareKey = queryKeys.quotes.compare(String(rfqId));
                context.compare = {
                    key: compareKey,
                    snapshot:
                        queryClient.getQueryData<QuoteComparisonRow[]>(
                            compareKey,
                        ),
                };

                if (context.compare.snapshot) {
                    queryClient.setQueryData<QuoteComparisonRow[]>(
                        compareKey,
                        (current) =>
                            current?.map((row) =>
                                matchesQuote(row.quote.id, quoteId)
                                    ? {
                                          ...row,
                                          quote: {
                                              ...row.quote,
                                              isShortlisted: shortlist,
                                          },
                                      }
                                    : row,
                            ) ?? current,
                    );
                }

                const listEntries = queryClient.getQueriesData<UseQuotesResult>(
                    {
                        predicate: (query) =>
                            isQuoteListQueryKey(query.queryKey, String(rfqId)),
                    },
                );

                context.lists = listEntries.map(([key, snapshot]) => ({
                    key,
                    snapshot,
                }));
                applyListOptimisticUpdate(
                    queryClient,
                    listEntries,
                    quoteId,
                    shortlist,
                );
            }

            return context;
        },
        onError: (_error, _variables, context) => {
            if (!context) {
                return;
            }

            if (context.detail?.snapshot) {
                queryClient.setQueryData(
                    context.detail.key,
                    context.detail.snapshot,
                );
            }

            context.lists?.forEach(({ key, snapshot }) => {
                queryClient.setQueryData(key, snapshot);
            });

            if (context.compare?.snapshot) {
                queryClient.setQueryData(
                    context.compare.key,
                    context.compare.snapshot,
                );
            }
        },
        onSuccess: (quote) => {
            syncQuoteCaches(queryClient, quote);
        },
    });
}

function syncQuoteCaches(queryClient: QueryClient, updated: Quote): void {
    const detailKey = queryKeys.quotes.detail(String(updated.id));
    queryClient.setQueryData<UseQuoteResult>(detailKey, (current) => {
        if (!current) {
            return current;
        }

        return {
            ...current,
            quote: {
                ...current.quote,
                ...updated,
            },
        };
    });

    if (updated.rfqId == null) {
        return;
    }

    queryClient.setQueriesData<UseQuotesResult>(
        {
            predicate: (query) =>
                isQuoteListQueryKey(query.queryKey, String(updated.rfqId)),
        },
        (current) => {
            if (!current) {
                return current;
            }

            const items = Array.isArray(current.items) ? current.items : [];
            if (items.length === 0) {
                return current;
            }

            const nextItems = items.map((quote) =>
                quote.id === updated.id ? { ...quote, ...updated } : quote,
            );
            return { ...current, items: nextItems };
        },
    );

    queryClient.invalidateQueries({
        predicate: (query) =>
            isQuoteListQueryKey(query.queryKey, String(updated.rfqId)),
    });

    queryClient.setQueryData<QuoteComparisonRow[]>(
        queryKeys.quotes.compare(String(updated.rfqId)),
        (current) => {
            if (!current) {
                return current;
            }

            return current.map((row) =>
                row.quote.id === updated.id
                    ? {
                          ...row,
                          quote: {
                              ...row.quote,
                              ...updated,
                          },
                      }
                    : row,
            );
        },
    );

    queryClient.invalidateQueries({
        queryKey: queryKeys.quotes.compare(String(updated.rfqId)),
    });
}

function normalizeQuotePayload(raw: unknown): unknown {
    if (!raw || typeof raw !== 'object') {
        return raw;
    }

    const draft = raw as Record<string, unknown>;

    return {
        ...draft,
        attachments: Array.isArray(draft.attachments) ? draft.attachments : [],
        revisions: Array.isArray(draft.revisions) ? draft.revisions : [],
        items: Array.isArray(draft.items) ? draft.items : [],
    };
}

function isQuoteListQueryKey(key: unknown, rfqId: string): boolean {
    if (!Array.isArray(key)) {
        return false;
    }

    return (
        key.length >= 4 &&
        key[0] === queryKeys.quotes.root()[0] &&
        key[1] === 'rfq' &&
        key[2] === rfqId &&
        key[3] === 'list'
    );
}

function applyListOptimisticUpdate(
    queryClient: QueryClient,
    entries: Array<[readonly unknown[], UseQuotesResult | undefined]>,
    quoteId: string | number,
    shortlist: boolean,
): void {
    entries.forEach(([key, data]) => {
        if (!data) {
            return;
        }

        const items = Array.isArray(data.items) ? data.items : [];
        if (items.length === 0) {
            return;
        }

        queryClient.setQueryData<UseQuotesResult>(key, {
            ...data,
            items: items.map((quote) =>
                matchesQuote(quote.id, quoteId)
                    ? {
                          ...quote,
                          isShortlisted: shortlist,
                      }
                    : quote,
            ),
        });
    });
}

function matchesQuote(
    candidateId: string | number,
    targetId: string | number,
): boolean {
    return String(candidateId) === String(targetId);
}
