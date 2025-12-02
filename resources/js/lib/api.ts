import axios, { AxiosError } from 'axios';

export class ApiError extends Error {
    status?: number;
    errors?: Record<string, string[]>;

    constructor(message: string, status?: number, errors?: Record<string, string[]>) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
    }
}

interface Envelope<T> {
    status: 'success' | 'error';
    message?: string | null;
    data?: T;
    errors?: Record<string, string[]> | null;
    meta?: Record<string, unknown> | null;
}

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null && !Array.isArray(value);

const mergeMeta = (
    payloadMeta: unknown,
    envelopeMeta: Record<string, unknown> | null | undefined,
): Record<string, unknown> | undefined => {
    const base: Record<string, unknown> = isRecord(payloadMeta) ? { ...payloadMeta } : {};

    if (envelopeMeta && typeof envelopeMeta === 'object') {
        const cursor = envelopeMeta.cursor;

        if (isRecord(cursor)) {
            const nextValue = cursor.nextCursor ?? cursor.next_cursor;
            const prevValue = cursor.prevCursor ?? cursor.prev_cursor;

            if (nextValue !== undefined) {
                if (base.nextCursor === undefined) {
                    base.nextCursor = nextValue;
                }
                if (base.next_cursor === undefined) {
                    base.next_cursor = nextValue;
                }
            }

            if (prevValue !== undefined) {
                if (base.prevCursor === undefined) {
                    base.prevCursor = prevValue;
                }
                if (base.prev_cursor === undefined) {
                    base.prev_cursor = prevValue;
                }
            }
        }

        const existingEnvelope = isRecord(base.envelope) ? base.envelope : undefined;

        base.envelope = {
            ...(existingEnvelope ?? {}),
            ...envelopeMeta,
        };
    }

    return Object.keys(base).length > 0 ? base : undefined;
};

export const api = axios.create({
    baseURL: '/api',
    headers: {
        Accept: 'application/json',
    },
    withCredentials: true,
});

api.interceptors.response.use(
    (response) => {
        const envelope = response.data as Envelope<unknown> | undefined;

        if (!envelope) {
            return response.data;
        }

        if (envelope.status === 'success') {
            const payload = envelope.data ?? null;

            if (!envelope.meta) {
                return payload;
            }

            if (payload && typeof payload === 'object' && !Array.isArray(payload)) {
                const payloadRecord = payload as Record<string, unknown>;
                const mergedMeta = mergeMeta(payloadRecord.meta, envelope.meta);

                if (mergedMeta) {
                    return {
                        ...payloadRecord,
                        meta: mergedMeta,
                    };
                }

                return { ...payloadRecord };
            }

            const mergedMeta = mergeMeta(undefined, envelope.meta);

            if (mergedMeta) {
                return {
                    data: payload,
                    meta: mergedMeta,
                };
            }

            return payload;
        }

        throw new ApiError(envelope.message ?? 'Request failed', response.status, envelope.errors ?? undefined);
    },
    (error: AxiosError<Envelope<unknown>>) => {
        if (error.response) {
            const { data, status } = error.response;
            const message = data?.message ?? error.message ?? 'Request failed';
            const errors = data?.errors ?? undefined;

            throw new ApiError(message, status, errors);
        }

        throw new ApiError(error.message ?? 'Network error');
    },
);

export function buildQuery(params: Record<string, unknown> = {}): string {
    const searchParams = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value === undefined || value === null) {
            return;
        }

        if (Array.isArray(value)) {
            value.forEach((item) => {
                if (item !== undefined && item !== null) {
                    searchParams.append(`${key}[]`, String(item));
                }
            });
            return;
        }

        if (typeof value === 'object') {
            Object.entries(value as Record<string, unknown>).forEach(([nestedKey, nestedValue]) => {
                if (nestedValue !== undefined && nestedValue !== null) {
                    searchParams.append(`${key}[${nestedKey}]`, String(nestedValue));
                }
            });
            return;
        }

        searchParams.append(key, String(value));
    });

    const query = searchParams.toString();

    return query ? `?${query}` : '';
}
