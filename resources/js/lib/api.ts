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

            if (envelope.meta) {
                if (payload && typeof payload === 'object' && !Array.isArray(payload)) {
                    return {
                        ...(payload as Record<string, unknown>),
                        meta: envelope.meta,
                    };
                }

                return {
                    data: payload,
                    meta: envelope.meta,
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
