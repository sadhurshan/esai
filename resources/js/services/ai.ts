import { api, ApiError } from '@/lib/api';

export interface AiResponse<TData = unknown> {
    status: 'success' | 'error';
    message?: string;
    data?: TData | null;
    errors?: Record<string, string[]> | null;
}

export interface ForecastPayload extends Record<string, unknown> {
    part_id: number;
    history: Array<{ date: string; quantity: number }>;
    horizon: number;
    entity_type?: string | null;
    entity_id?: number | null;
}

export interface SupplierRiskPayload extends Record<string, unknown> {
    supplier: Record<string, unknown>;
    entity_type?: string | null;
    entity_id?: number | null;
}

export interface IndexDocumentPayload extends Record<string, unknown> {
    doc_id: number;
    doc_version: string;
}

export interface SemanticSearchFilters extends Record<string, unknown> {
    source_type?: string;
    doc_id?: string | number;
    tags?: string[];
}

export interface SemanticSearchPayload extends Record<string, unknown> {
    query: string;
    top_k?: number;
    filters?: SemanticSearchFilters;
}

export interface SemanticSearchHit {
    doc_id: string;
    doc_version: string;
    chunk_id: string;
    score: number;
    title: string;
    snippet: string;
    metadata?: Record<string, unknown>;
}

export interface SemanticSearchResponse {
    hits: SemanticSearchHit[];
    meta?: Record<string, unknown>;
}

export interface AnswerQuestionPayload extends SemanticSearchPayload {}

export interface AnswerQuestionResponse {
    answer_markdown: string;
    answer?: string;
    citations: SemanticSearchHit[];
    needs_human_review: boolean;
    confidence?: number | null;
    warnings?: string[];
    meta?: Record<string, unknown>;
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

export const indexDocument = async (
    payload: IndexDocumentPayload,
): Promise<AiResponse<{ doc_id: number; doc_version: string }>> => {
    return handleRequest<IndexDocumentPayload, { doc_id: number; doc_version: string }>(
        '/v1/admin/ai/reindex-document',
        payload,
    );
};

export const semanticSearch = async <TData = SemanticSearchResponse>(
    payload: SemanticSearchPayload,
): Promise<AiResponse<TData>> => {
    return handleRequest<SemanticSearchPayload, TData>('/copilot/search', payload);
};

export const answerQuestion = async <TData = AnswerQuestionResponse>(
    payload: AnswerQuestionPayload,
): Promise<AiResponse<TData>> => {
    return handleRequest<AnswerQuestionPayload, TData>('/copilot/answer', payload);
};
