import type { HTTPQuery } from '../../sdk/ts-client/generated/runtime';

interface ApiEnvelope<T> {
    status?: 'success' | 'error';
    message?: string | null;
    data?: T;
    meta?: Record<string, unknown> | null;
}

export function sanitizeQuery(params: Record<string, unknown>): HTTPQuery {
    return Object.entries(params).reduce<HTTPQuery>((acc, [key, value]) => {
        if (value === undefined || value === null || value === '') {
            return acc;
        }

        if (Array.isArray(value)) {
            acc[`${key}[]`] = value.filter((entry) => entry !== undefined && entry !== null) as HTTPQuery[keyof HTTPQuery];
            return acc;
        }

        if (typeof value === 'object') {
            Object.entries(value).forEach(([nestedKey, nestedValue]) => {
                if (nestedValue !== undefined && nestedValue !== null && nestedValue !== '') {
                    acc[`${key}[${nestedKey}]`] = nestedValue as HTTPQuery[keyof HTTPQuery];
                }
            });
            return acc;
        }

        acc[key] = value as HTTPQuery[keyof HTTPQuery];
        return acc;
    }, {});
}

export async function parseEnvelope<T>(response: Response): Promise<T> {
    const payload = (await response.json()) as ApiEnvelope<T> | T;

    if (payload && typeof payload === 'object' && 'status' in payload) {
        const envelope = payload as ApiEnvelope<T>;

        if (envelope.status === 'success') {
            const data = envelope.data ?? ({} as T);

            if (envelope.meta) {
                if (data && typeof data === 'object' && !Array.isArray(data)) {
                    return {
                        ...(data as Record<string, unknown>),
                        meta: envelope.meta,
                    } as unknown as T;
                }

                return {
                    data,
                    meta: envelope.meta,
                } as unknown as T;
            }

            return data;
        }

        throw new Error(envelope.message ?? 'Request failed');
    }

    return payload as T;
}
