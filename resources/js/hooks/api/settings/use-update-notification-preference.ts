import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type {
    NotificationPreferenceMap,
    NotificationPreferenceResponseItem,
    NotificationEventType,
    NotificationChannel,
    NotificationDigestFrequency,
} from '@/types/notifications';

export interface UpdateNotificationPreferencePayload {
    event_type: NotificationEventType;
    channel: NotificationChannel;
    digest: NotificationDigestFrequency;
}

export function useUpdateNotificationPreference(): UseMutationResult<
    NotificationPreferenceResponseItem,
    ApiError,
    UpdateNotificationPreferencePayload
> {
    const queryClient = useQueryClient();

    return useMutation<NotificationPreferenceResponseItem, ApiError, UpdateNotificationPreferencePayload>({
        mutationFn: async (payload) => {
            const data = (await api.put<NotificationPreferenceResponseItem>(
                '/notification-preferences',
                payload,
            )) as unknown as NotificationPreferenceResponseItem;
            return data;
        },
        onSuccess: (data) => {
            queryClient.setQueryData<NotificationPreferenceMap | undefined>(
                queryKeys.settings.notificationPreferences(),
                (existing) => ({
                    ...(existing ?? {}),
                    [data.event_type]: {
                        channel: data.channel,
                        digest: data.digest,
                    },
                }),
            );
        },
    });
}
