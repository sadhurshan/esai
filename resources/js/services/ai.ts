import { api, ApiError } from '@/lib/api';

export interface AiResponse<TData = unknown> {
    status: 'success' | 'error';
    message?: string;
    data?: TData | null;
    errors?: Record<string, string[]> | null;
}

export interface ForecastPayload {
    part_id: number;
    history: Array<{ date: string; quantity: number }>;
    horizon: number;
    entity_type?: string | null;
    entity_id?: number | null;
}

export interface SupplierRiskPayload {
    supplier: Record<string, unknown>;
    entity_type?: string | null;
    entity_id?: number | null;
}

const normalizeError = (error: unknown): ApiError => {
    if (error instanceof ApiError) {
        return error;
    }

    return new ApiError(error instanceof Error ? error.message : 'AI request failed');
};

const handleRequest = async <TPayload extends Record<string, unknown>, TData>(
    path: string,
    payload: TPayload,
): Promise<AiResponse<TData>> => {
    try {
        const data = (await api.post(path, payload)) as TData;

        return {
            status: 'success',
            message: 'AI request completed.',
            data,
            errors: null,
        };
    } catch (error) {
        throw normalizeError(error);
    }
};

export const getForecast = async <TData = Record<string, unknown>>(payload: ForecastPayload): Promise<AiResponse<TData>> => {
    return handleRequest<ForecastPayload, TData>('/ai/forecast', payload);
};

export const getSupplierRisk = async <TData = Record<string, unknown>>(
    payload: SupplierRiskPayload,
): Promise<AiResponse<TData>> => {
    return handleRequest<SupplierRiskPayload, TData>('/ai/supplier-risk', payload);
};
