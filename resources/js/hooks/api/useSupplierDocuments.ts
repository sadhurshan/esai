import { useMutation, useQuery, useQueryClient, type UseMutationResult, type UseQueryResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';

export type SupplierDocumentType =
    | 'iso9001'
    | 'iso14001'
    | 'as9100'
    | 'itar'
    | 'reach'
    | 'rohs'
    | 'insurance'
    | 'nda'
    | 'other';

export interface SupplierDocument {
    id: number;
    supplier_id: number;
    company_id: number;
    document_id?: number | null;
    type: SupplierDocumentType;
    status: 'valid' | 'expiring' | 'expired';
    path?: string | null;
    download_url?: string | null;
    mime: string;
    filename?: string | null;
    size_bytes: number;
    issued_at: string | null;
    expires_at: string | null;
    created_at?: string | null;
    updated_at?: string | null;
}

interface SupplierDocumentCollection {
    items: SupplierDocument[];
}

export interface UploadSupplierDocumentInput {
    file: File;
    type: SupplierDocumentType;
    issued_at?: string;
    expires_at?: string;
}

export function useSupplierDocuments(): UseQueryResult<SupplierDocumentCollection, ApiError> {
    return useQuery<SupplierDocumentCollection, ApiError>({
        queryKey: queryKeys.me.supplierDocuments(),
        queryFn: async () =>
            (await api.get<SupplierDocumentCollection>('/me/supplier-documents')) as unknown as SupplierDocumentCollection,
        staleTime: 15_000,
    });
}

export function useUploadSupplierDocument(): UseMutationResult<SupplierDocument, ApiError, UploadSupplierDocumentInput> {
    const queryClient = useQueryClient();

    return useMutation<SupplierDocument, ApiError, UploadSupplierDocumentInput>({
        mutationFn: async (input) => {
            const formData = new FormData();
            formData.append('type', input.type);
            if (input.issued_at) {
                formData.append('issued_at', input.issued_at);
            }
            if (input.expires_at) {
                formData.append('expires_at', input.expires_at);
            }
            formData.append('document', input.file);

            return (await api.post<SupplierDocument>('/me/supplier-documents', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            })) as unknown as SupplierDocument;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: queryKeys.me.supplierDocuments() }).catch(() => {});
        },
    });
}

export function useDeleteSupplierDocument(): UseMutationResult<void, ApiError, number> {
    const queryClient = useQueryClient();

    return useMutation<void, ApiError, number>({
        mutationFn: async (documentId) => {
            await api.delete(`/me/supplier-documents/${documentId}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: queryKeys.me.supplierDocuments() }).catch(() => {});
        },
    });
}
