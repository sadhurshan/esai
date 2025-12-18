import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import { api, ApiError } from '@/lib/api';

interface DeleteDocumentInput {
    documentId: number;
    invalidateKey?: readonly unknown[];
}

export function useDeleteDocument(): UseMutationResult<void, ApiError | Error, DeleteDocumentInput> {
    const queryClient = useQueryClient();
    const { notifyPlanLimit } = useAuth();

    return useMutation<void, ApiError | Error, DeleteDocumentInput>({
        mutationFn: async ({ documentId }) => {
            if (!Number.isFinite(documentId) || documentId <= 0) {
                throw new Error('Invalid attachment reference.');
            }

            await api.delete(`/documents/${documentId}`);
        },
        onSuccess: (_, variables) => {
            publishToast({
                variant: 'success',
                title: 'Attachment removed',
                description: 'The document was deleted.',
            });

            if (variables.invalidateKey) {
                void queryClient.invalidateQueries({ queryKey: variables.invalidateKey });
            }
        },
        onError: (error) => {
            if (error instanceof ApiError && (error.status === 402 || error.status === 403)) {
                notifyPlanLimit({
                    code: 'documents',
                    message: error.message ?? 'Upgrade required to manage documents.',
                });
            }

            publishToast({
                variant: 'destructive',
                title: 'Unable to delete attachment',
                description: error.message ?? 'We could not remove that document.',
            });
        },
    });
}
