import axios, { AxiosError, type AxiosRequestConfig, type InternalAxiosRequestConfig } from 'axios';

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

const AUTH_STORAGE_KEY = 'esai.auth.state';
const ACTIVE_PERSONA_HEADER = 'X-Active-Persona';

interface StoredAuthSnapshot {
    activePersonaKey?: unknown;
}

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

const CSRF_COOKIE_NAME = 'XSRF-TOKEN';
const CSRF_HEADER_NAME = 'X-XSRF-TOKEN';
let csrfCookiePromise: Promise<void> | null = null;

const escapeCookieName = (name: string): string => name.replace(/([.$?*|{}()\[\]\/+^])/g, '\\$1');

const readCookie = (name: string): string | undefined => {
    if (typeof document === 'undefined' || typeof document.cookie !== 'string') {
        return undefined;
    }

    const pattern = new RegExp(`(?:^|; )${escapeCookieName(name)}=([^;]*)`);
    const match = document.cookie.match(pattern);

    if (!match) {
        return undefined;
    }

    try {
        return decodeURIComponent(match[1]);
    } catch (error) {
        console.warn('Failed to decode cookie', error);
        return undefined;
    }
};

const ensureCsrfCookie = async (): Promise<void> => {
    if (typeof document === 'undefined') {
        return;
    }

    if (readCookie(CSRF_COOKIE_NAME)) {
        return;
    }

    if (!csrfCookiePromise) {
        csrfCookiePromise = axios
            .get('/sanctum/csrf-cookie', {
                withCredentials: true,
            })
            .then(() => {
                csrfCookiePromise = null;
            })
            .catch((error) => {
                csrfCookiePromise = null;
                throw error;
            });
    }

    await csrfCookiePromise;
};

const attachCsrfHeader = <TConfig extends AxiosRequestConfig>(config: TConfig): TConfig => {
    const token = readCookie(CSRF_COOKIE_NAME);

    if (!token) {
        return config;
    }

    if (!config.headers) {
        config.headers = {};
    }

    const headers = config.headers as Record<string, unknown>;

    if (!headers[CSRF_HEADER_NAME]) {
        headers[CSRF_HEADER_NAME] = token;
    }

    return config;
};

const attachActivePersonaHeader = <TConfig extends AxiosRequestConfig>(config: TConfig): TConfig => {
    const personaKey = readStoredActivePersonaKey();

    if (!personaKey) {
        return config;
    }

    if (!config.headers) {
        config.headers = {};
    }

    const headers = config.headers as Record<string, unknown>;
    headers[ACTIVE_PERSONA_HEADER] = personaKey;

    return config;
};

function readStoredActivePersonaKey(): string | null {
    if (typeof window === 'undefined' || typeof window.localStorage === 'undefined') {
        return null;
    }

    try {
        const raw = window.localStorage.getItem(AUTH_STORAGE_KEY);
        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw) as StoredAuthSnapshot | null;
        const key = parsed?.activePersonaKey;

        return typeof key === 'string' && key.length > 0 ? key : null;
    } catch {
        return null;
    }
}

const SAFE_METHODS = new Set(['GET', 'HEAD', 'OPTIONS']);

const methodRequiresCsrf = (config: AxiosRequestConfig): boolean => {
    const method = (config.method ?? 'get').toUpperCase();
    return !SAFE_METHODS.has(method);
};

api.interceptors.request.use(
    async (config: InternalAxiosRequestConfig) => {
        let nextConfig = config;

        if (typeof document !== 'undefined') {
            if (methodRequiresCsrf(nextConfig)) {
                await ensureCsrfCookie();
            }

            nextConfig = attachCsrfHeader(nextConfig);
        }

        return attachActivePersonaHeader(nextConfig);
    },
    (error) => Promise.reject(error),
);

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
