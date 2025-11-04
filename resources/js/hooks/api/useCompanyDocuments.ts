import { useMutation, useQuery, useQueryClient, type UseMutationResult, type UseQueryResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { CompanyDocument, CompanyDocumentType } from '@/types/company';

export interface CompanyDocumentResponse {
    id: number;
    company_id: number;
    type: CompanyDocumentType;
    path: string;
    verified_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export const mapCompanyDocument = (
    payload: CompanyDocumentResponse,
): CompanyDocument => ({
    id: payload.id,
    companyId: payload.company_id,
    type: payload.type,
    path: payload.path,
    verifiedAt: payload.verified_at,
    createdAt: payload.created_at,
    updatedAt: payload.updated_at,
});

interface CompanyDocumentIndexResponse {
    items: CompanyDocumentResponse[];
}

export function useCompanyDocuments(
    companyId?: number | null,
): UseQueryResult<CompanyDocument[], ApiError> {
    return useQuery<CompanyDocumentIndexResponse, ApiError, CompanyDocument[]>({
        queryKey: queryKeys.companies.documents(companyId ?? 0),
        enabled: Boolean(companyId) && (companyId ?? 0) > 0,
        queryFn: async () =>
            (await api.get<CompanyDocumentIndexResponse>(
                `/companies/${companyId}/documents`,
            )) as unknown as CompanyDocumentIndexResponse,
        select: (response) => response.items.map(mapCompanyDocument),
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
