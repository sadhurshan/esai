import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { User } from '@/types';

export function useProfile(): UseQueryResult<User, ApiError> {
    return useQuery<User, ApiError>({
        queryKey: queryKeys.me.profile(),
        queryFn: async () => {
            const data = (await api.get<User>('/me/profile')) as unknown as User;
            return data;
        },
        staleTime: 60 * 1000,
    });
}
