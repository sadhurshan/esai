import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { NotificationPreferenceMap } from '@/types/notifications';

export function useNotificationPreferences(): UseQueryResult<
    NotificationPreferenceMap,
    ApiError
> {
    return useQuery<NotificationPreferenceMap, ApiError>({
        queryKey: queryKeys.settings.notificationPreferences(),
        queryFn: async () => {
            const data = (await api.get<NotificationPreferenceMap>(
                '/notification-preferences',
            )) as NotificationPreferenceMap | undefined;
            return data ?? {};
        },
        staleTime: 60 * 1000,
    });
}
