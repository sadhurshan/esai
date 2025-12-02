import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { User } from '@/types';

export interface UpdateProfilePayload {
    name: string;
    email: string;
    job_title?: string | null;
    phone?: string | null;
    locale?: string | null;
    timezone?: string | null;
    avatar_path?: string | null;
}

export function useUpdateProfile(): UseMutationResult<User, ApiError, UpdateProfilePayload> {
    const queryClient = useQueryClient();

    return useMutation<User, ApiError, UpdateProfilePayload>({
        mutationFn: async (payload) => {
            const data = (await api.patch<User>('/me/profile', payload)) as unknown as User;
            return data;
        },
        onSuccess: (data) => {
            queryClient.setQueryData(queryKeys.me.profile(), data);
        },
    });
}
