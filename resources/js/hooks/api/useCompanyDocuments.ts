import {
    useMutation,
    useQuery,
    useQueryClient,
    type UseMutationResult,
    type UseQueryResult,
} from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { toCursorMeta } from '@/lib/pagination';
import { queryKeys } from '@/lib/queryKeys';
import type {
    CompanyDocument,
    CompanyDocumentCollection,
    CompanyDocumentType,
} from '@/types/company';

export interface CompanyDocumentResponse {
    id: number;
    company_id: number;
    document_id: number | null;
    type: CompanyDocumentType;
    filename: string | null;
    mime: string | null;
    size_bytes: number | null;
    download_url: string | null;
    verified_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export const mapCompanyDocument = (
    payload: CompanyDocumentResponse,
): CompanyDocument => ({
    id: payload.id,
    companyId: payload.company_id,
    documentId: payload.document_id ?? undefined,
    type: payload.type,
    filename: payload.filename ?? undefined,
    mime: payload.mime ?? undefined,
    sizeBytes: payload.size_bytes ?? undefined,
    downloadUrl: payload.download_url ?? undefined,
    verifiedAt: payload.verified_at,
    createdAt: payload.created_at,
    updatedAt: payload.updated_at,
});

interface CompanyDocumentIndexResponse {
    items: CompanyDocumentResponse[];
    meta?: Record<string, unknown>;
}

export interface CompanyDocumentQueryOptions {
    companyId?: number | null;
    cursor?: string | null;
    enabled?: boolean;
}

type UseCompanyDocumentsArgs =
    | CompanyDocumentQueryOptions
    | number
    | null
    | undefined;

function normalizeCompanyDocumentArgs(
    args?: UseCompanyDocumentsArgs,
): CompanyDocumentQueryOptions {
    if (typeof args === 'number' || args === null || args === undefined) {
        return { companyId: args ?? undefined };
    }

    return args;
}

export function useCompanyDocuments(
    args?: UseCompanyDocumentsArgs,
): UseQueryResult<CompanyDocumentCollection, ApiError> {
    const {
        companyId,
        cursor,
        enabled = true,
    } = normalizeCompanyDocumentArgs(args);
    const normalizedCompanyId = companyId ?? 0;
    const baseKey = queryKeys.companies.documents(normalizedCompanyId);
    const queryKey = [...baseKey, cursor ?? null] as const;

    return useQuery<
        CompanyDocumentIndexResponse,
        ApiError,
        CompanyDocumentCollection
    >({
        queryKey,
        enabled: enabled && Boolean(companyId) && normalizedCompanyId > 0,
        queryFn: async () =>
            (await api.get<CompanyDocumentIndexResponse>(
                `/companies/${normalizedCompanyId}/documents`,
                {
                    params: cursor ? { cursor } : undefined,
                },
            )) as unknown as CompanyDocumentIndexResponse,
        select: (response) => ({
            items: response.items.map(mapCompanyDocument),
            meta: toCursorMeta(response.meta),
        }),
        staleTime: 15_000,
    });
}

export interface UploadCompanyDocumentInput {
    companyId: number;
    type: CompanyDocumentType;
    file: File;
}

export function useUploadCompanyDocument(): UseMutationResult<
    CompanyDocument,
    ApiError,
    UploadCompanyDocumentInput
> {
    const queryClient = useQueryClient();

    return useMutation<CompanyDocument, ApiError, UploadCompanyDocumentInput>({
        mutationFn: async ({ companyId, type, file }) => {
            const formData = new FormData();
            formData.append('type', type);
            formData.append('document', file);

            const response = (await api.post<CompanyDocumentResponse>(
                `/companies/${companyId}/documents`,
                formData,
                {
                    headers: { 'Content-Type': 'multipart/form-data' },
                },
            )) as unknown as CompanyDocumentResponse;

            return mapCompanyDocument(response);
        },
        onSuccess: (document) => {
            queryClient.invalidateQueries({
                queryKey: queryKeys.companies.documents(document.companyId),
            });
            queryClient.invalidateQueries({
                queryKey: queryKeys.companies.detail(document.companyId),
            });
        },
    });
}

export interface DeleteCompanyDocumentInput {
    companyId: number;
    documentId: number;
}

export function useDeleteCompanyDocument(): UseMutationResult<
    void,
    ApiError,
    DeleteCompanyDocumentInput
> {
    const queryClient = useQueryClient();

    return useMutation<void, ApiError, DeleteCompanyDocumentInput>({
        mutationFn: async ({ companyId, documentId }) => {
            await api.delete(`/companies/${companyId}/documents/${documentId}`);
        },
        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({
                queryKey: queryKeys.companies.documents(variables.companyId),
            });
        },
    });
}
