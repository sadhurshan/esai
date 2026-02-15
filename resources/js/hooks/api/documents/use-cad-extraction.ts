import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';

export interface CadExtractionResult {
    documentId: number;
    documentVersion: number;
    status: 'pending' | 'completed' | 'error';
    extracted?: {
        materials?: string[];
        finishes?: string[];
        processes?: string[];
        tolerances?: string[];
    } | null;
    gdtFlags?: {
        complex?: boolean;
        signals?: string[];
    } | null;
    similarParts?: Array<{
        id: number;
        part_number?: string | null;
        name?: string | null;
        spec?: string | null;
    }>;
    extractedAt?: string | null;
    lastError?: string | null;
}

interface CadExtractionPayload {
    document_id: number;
    document_version: number;
    status: 'pending' | 'completed' | 'error';
    extracted?: CadExtractionResult['extracted'];
    gdt_flags?: CadExtractionResult['gdtFlags'];
    similar_parts?: CadExtractionResult['similarParts'];
    extracted_at?: string | null;
    last_error?: string | null;
}

export function useCadExtraction(
    documentId?: string | number,
): UseQueryResult<CadExtractionResult, ApiError> {
    return useQuery<CadExtractionResult, ApiError>({
        queryKey: queryKeys.documents.cadExtraction(String(documentId ?? '')),
        enabled: Boolean(documentId),
        queryFn: async () => {
            if (!documentId) {
                throw new Error('documentId is required');
            }

            const payload = (await api.get<CadExtractionPayload>(
                `/documents/${documentId}/cad-extraction`,
            )) as unknown as CadExtractionPayload;

            return {
                documentId: payload.document_id,
                documentVersion: payload.document_version,
                status: payload.status,
                extracted: payload.extracted ?? null,
                gdtFlags: payload.gdt_flags ?? null,
                similarParts: payload.similar_parts ?? [],
                extractedAt: payload.extracted_at ?? null,
                lastError: payload.last_error ?? null,
            };
        },
        staleTime: 30_000,
    });
}
