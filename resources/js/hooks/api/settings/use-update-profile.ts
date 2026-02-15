import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

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
    avatar?: File | null;
}

export function useUpdateProfile(): UseMutationResult<
    User,
    ApiError,
    UpdateProfilePayload
> {
    const queryClient = useQueryClient();

    return useMutation<User, ApiError, UpdateProfilePayload>({
        mutationFn: async (payload) => {
            const data = (await api.post<User>(
                '/me/profile',
                buildProfileFormData(payload),
            )) as unknown as User;
            return data;
        },
        onSuccess: (data) => {
            queryClient.setQueryData(queryKeys.me.profile(), data);
        },
    });
}

const buildProfileFormData = (payload: UpdateProfilePayload): FormData => {
    const formData = new FormData();

    formData.append('_method', 'PATCH');
    formData.append('name', payload.name);
    formData.append('email', payload.email);

    appendNullableString(formData, 'job_title', payload.job_title);
    appendNullableString(formData, 'phone', payload.phone);
    appendNullableString(formData, 'locale', payload.locale);
    appendNullableString(formData, 'timezone', payload.timezone);
    appendNullableString(formData, 'avatar_path', payload.avatar_path);

    if (payload.avatar instanceof File) {
        formData.append('avatar', payload.avatar);
    }

    return formData;
};

const appendNullableString = (
    formData: FormData,
    key: string,
    value: string | null | undefined,
): void => {
    if (value === undefined) {
        return;
    }

    formData.append(key, value ?? '');
};
