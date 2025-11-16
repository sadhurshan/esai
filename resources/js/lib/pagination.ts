export interface CursorPaginationMeta {
    nextCursor?: string | null;
    prevCursor?: string | null;
    perPage?: number;
    raw?: Record<string, unknown>;
}

export interface OffsetPaginationMeta {
    total?: number;
    perPage?: number;
    currentPage?: number;
    lastPage?: number;
    raw?: Record<string, unknown>;
}

export function toCursorMeta(meta?: Record<string, unknown> | null): CursorPaginationMeta | undefined {
    if (!meta || typeof meta !== 'object') {
        return undefined;
    }

    const nextCandidate =
        (meta.nextCursor as string | null | undefined) ??
        (meta.next_cursor as string | null | undefined) ??
        (meta.next as string | null | undefined);
    const prevCandidate =
        (meta.prevCursor as string | null | undefined) ??
        (meta.prev_cursor as string | null | undefined) ??
        (meta.previous as string | null | undefined);

    const perPageCandidate =
        typeof meta.perPage === 'number'
            ? (meta.perPage as number)
            : typeof meta.per_page === 'number'
              ? (meta.per_page as number)
              : undefined;

    return {
        nextCursor: typeof nextCandidate === 'string' ? nextCandidate : nextCandidate ?? undefined,
        prevCursor: typeof prevCandidate === 'string' ? prevCandidate : prevCandidate ?? undefined,
        perPage: perPageCandidate,
        raw: meta as Record<string, unknown>,
    };
}

export function toOffsetMeta(meta?: Record<string, unknown> | null): OffsetPaginationMeta | undefined {
    if (!meta || typeof meta !== 'object') {
        return undefined;
    }

    const totalCandidate = typeof meta.total === 'number' ? (meta.total as number) : undefined;
    const perPageCandidate =
        typeof meta.perPage === 'number'
            ? (meta.perPage as number)
            : typeof meta.per_page === 'number'
              ? (meta.per_page as number)
              : undefined;
    const currentPageCandidate =
        typeof meta.currentPage === 'number'
            ? (meta.currentPage as number)
            : typeof meta.current_page === 'number'
              ? (meta.current_page as number)
              : undefined;
    const lastPageCandidate =
        typeof meta.lastPage === 'number'
            ? (meta.lastPage as number)
            : typeof meta.last_page === 'number'
              ? (meta.last_page as number)
              : undefined;

    return {
        total: totalCandidate,
        perPage: perPageCandidate,
        currentPage: currentPageCandidate,
        lastPage: lastPageCandidate,
        raw: meta as Record<string, unknown>,
    };
}
