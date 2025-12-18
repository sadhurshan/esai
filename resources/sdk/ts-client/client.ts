import { Configuration, ConfigurationParameters, FetchAPI, ResponseError } from './generated';

export interface RetryOptions {
    maxAttempts: number;
    initialDelayMs: number;
    maxDelayMs: number;
}

export interface ClientOptions {
    baseUrl?: string;
    bearerToken?: TokenSource;
    apiKey?: TokenSource;
    activePersona?: TokenSource;
    fetch?: FetchAPI;
    retry?: Partial<RetryOptions>;
    defaultHeaders?: Record<string, string>;
}

type TokenSource = string | (() => MaybePromise<string | undefined>) | undefined;

type MaybePromise<T> = T | Promise<T>;

const DEFAULT_RETRY: RetryOptions = {
    maxAttempts: 3,
    initialDelayMs: 250,
    maxDelayMs: 2_000,
};

export class HttpError extends ResponseError {
    constructor(response: Response, public readonly body: unknown, message?: string) {
        super(response, message ?? `Request failed with status ${response.status}`);
    }
}

export class TooManyRequestsError extends HttpError {
    constructor(response: Response, body: unknown, public readonly retryAfterMs: number, public readonly attempts: number) {
        super(response, body, 'Too many requests');
    }
}

export function createConfiguration(options: ClientOptions = {}): Configuration {
    const config: ConfigurationParameters = {
        basePath: options.baseUrl,
        headers: options.defaultHeaders,
        fetchApi: createAuthenticatedFetch(options),
    };

    return new Configuration(config);
}

export function createAuthenticatedFetch(options: ClientOptions = {}): FetchAPI {
    const retry = applyRetryDefaults(options.retry ?? {});
    const fetchImpl = resolveFetchImplementation(options.fetch);

    return async (input: RequestInfo | URL, init?: RequestInit): Promise<Response> => {
        const mergedInit: RequestInit = {
            credentials: 'include',
            ...init,
        };

        const request = new Request(input, mergedInit);
        const headers = new Headers(request.headers);

        applyDefaultHeaders(headers, options.defaultHeaders ?? {});
        attachXsrfHeader(headers, request.url);

        if (!headers.has('Authorization')) {
            const bearer = await resolveToken(options.bearerToken);
            if (bearer) {
                headers.set('Authorization', bearer.startsWith('Bearer ') ? bearer : `Bearer ${bearer}`);
            }
        }

        if (!headers.has('X-API-Key')) {
            const apiKey = await resolveToken(options.apiKey);
            if (apiKey) {
                headers.set('X-API-Key', apiKey);
            }
        }

        if (!headers.has('X-Active-Persona')) {
            const personaKey = await resolveToken(options.activePersona);
            if (personaKey) {
                headers.set('X-Active-Persona', personaKey);
            }
        }

        const authenticatedRequest = new Request(request, { headers });

        return fetchWithRetry(authenticatedRequest, fetchImpl, retry);
    };
}

function applyRetryDefaults(override: Partial<RetryOptions>): RetryOptions {
    return {
        maxAttempts: override.maxAttempts ?? DEFAULT_RETRY.maxAttempts,
        initialDelayMs: override.initialDelayMs ?? DEFAULT_RETRY.initialDelayMs,
        maxDelayMs: override.maxDelayMs ?? DEFAULT_RETRY.maxDelayMs,
    };
}

function resolveFetchImplementation(provided?: FetchAPI): FetchAPI {
    if (provided) {
        return provided;
    }

    if (typeof fetch === 'function') {
        return fetch.bind(globalThis);
    }

    throw new Error('No fetch implementation available. Provide ClientOptions.fetch when using this SDK outside environments with a global fetch.');
}

async function resolveToken(source: TokenSource): Promise<string | undefined> {
    if (source === undefined) {
        return undefined;
    }

    if (typeof source === 'function') {
        const result = await source();
        return result ?? undefined;
    }

    return source;
}

function applyDefaultHeaders(headers: Headers, defaults: Record<string, string>): void {
    Object.entries(defaults).forEach(([key, value]) => {
        if (!headers.has(key) && value !== undefined) {
            headers.set(key, value);
        }
    });
}

function attachXsrfHeader(headers: Headers, requestUrl: string): void {
    if (headers.has('X-XSRF-TOKEN')) {
        return;
    }

    const token = resolveXsrfToken(requestUrl);
    if (token) {
        headers.set('X-XSRF-TOKEN', token);
    }
}

function resolveXsrfToken(requestUrl: string): string | undefined {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return undefined;
    }

    const target = new URL(requestUrl, window.location.origin);
    if (target.origin !== window.location.origin) {
        return undefined;
    }

    const value = readCookie('XSRF-TOKEN');
    if (!value) {
        return undefined;
    }

    try {
        return decodeURIComponent(value);
    } catch {
        return value;
    }
}

function readCookie(name: string): string | undefined {
    if (typeof document === 'undefined') {
        return undefined;
    }

    const cookies = document.cookie ? document.cookie.split(';') : [];
    const prefix = `${name}=`;

    for (const cookie of cookies) {
        const trimmed = cookie.trim();
        if (trimmed.startsWith(prefix)) {
            return trimmed.substring(prefix.length);
        }
    }

    return undefined;
}

async function fetchWithRetry(request: Request, fetchImpl: FetchAPI, retry: RetryOptions): Promise<Response> {
    let attempt = 0;

    while (attempt < retry.maxAttempts) {
        const response = await fetchImpl(request.clone());

        if (response.status === 429) {
            attempt++;

            if (attempt >= retry.maxAttempts) {
                const body = await safeParseBody(response);
                const retryAfterMs = parseRetryAfter(response);
                throw new TooManyRequestsError(response, body, retryAfterMs, attempt);
            }

            const delay = calculateBackoffDelay(retry.initialDelayMs, retry.maxDelayMs, attempt - 1, response);
            await delayFor(delay, request.signal);
            continue;
        }

        if (!response.ok) {
            const body = await safeParseBody(response);
            throw new HttpError(response, body);
        }

        return response;
    }

    const fallbackResponse = await fetchImpl(request.clone());
    if (!fallbackResponse.ok) {
        const body = await safeParseBody(fallbackResponse);
        throw new HttpError(fallbackResponse, body);
    }

    return fallbackResponse;
}

function calculateBackoffDelay(initial: number, max: number, attempt: number, response: Response): number {
    const retryAfterMs = parseRetryAfter(response);
    if (retryAfterMs > 0) {
        return retryAfterMs;
    }

    const exponent = Math.max(attempt, 0);
    const delay = initial * Math.pow(2, exponent);
    return Math.min(delay, max);
}

function parseRetryAfter(response: Response): number {
    const header = response.headers.get('Retry-After');

    if (!header) {
        return 0;
    }

    const seconds = Number(header);
    if (!Number.isNaN(seconds)) {
        return Math.max(0, seconds * 1000);
    }

    const asDate = Date.parse(header);
    if (Number.isNaN(asDate)) {
        return 0;
    }

    const delta = asDate - Date.now();
    return delta > 0 ? delta : 0;
}

async function safeParseBody(response: Response): Promise<unknown> {
    const clone = response.clone();
    const contentType = clone.headers.get('content-type') ?? '';

    try {
        if (contentType.includes('application/json')) {
            return await clone.json();
        }

        return await clone.text();
    } catch (
        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        _error
    ) {
        return undefined;
    }
}

function delayFor(durationMs: number, signal?: AbortSignal | null): Promise<void> {
    if (durationMs <= 0) {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => {
            cleanup();
            resolve();
        }, durationMs);

        const onAbort = (): void => {
            cleanup();
            reject(signal?.reason ?? createAbortError());
        };

        const cleanup = (): void => {
            clearTimeout(timer);
            if (signal) {
                signal.removeEventListener('abort', onAbort);
            }
        };

        if (signal) {
            if (signal.aborted) {
                onAbort();
                return;
            }

            signal.addEventListener('abort', onAbort, { once: true });
        }
    });
}

function createAbortError(): Error {
    if (typeof DOMException === 'function') {
        return new DOMException('Aborted', 'AbortError');
    }

    const error = new Error('Aborted');
    error.name = 'AbortError';
    return error;
}
