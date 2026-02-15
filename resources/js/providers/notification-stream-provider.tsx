import { useAuth } from '@/contexts/auth-context';
import { createRealtimeClient } from '@/lib/echo-client';
import { queryKeys } from '@/lib/queryKeys';
import { useQueryClient } from '@tanstack/react-query';
import { useEffect, type PropsWithChildren } from 'react';

const NOTIFICATION_EVENT = '.App\\Events\\NotificationDispatched';
const NOTIFICATION_ROOT_KEY = ['notifications'] as const;

type NotificationStreamProviderProps = PropsWithChildren;

export function NotificationStreamProvider({
    children,
}: NotificationStreamProviderProps) {
    const { state, isAuthenticated } = useAuth();
    const queryClient = useQueryClient();

    useEffect(() => {
        if (!isAuthenticated || !state.user?.id || !state.token) {
            return;
        }

        const echo = createRealtimeClient({ token: state.token });

        if (!echo) {
            return;
        }

        const channelName = `users.${state.user.id}.notifications`;
        const channel = echo
            .private(channelName)
            .listen(NOTIFICATION_EVENT, () => {
                queryClient.invalidateQueries({
                    queryKey: NOTIFICATION_ROOT_KEY,
                });
                queryClient.invalidateQueries({
                    queryKey: queryKeys.notifications.badge(),
                });
            });

        return () => {
            channel.stopListening(NOTIFICATION_EVENT);

            const connector = echo.connector as {
                pusher?: {
                    connection?: {
                        state?: string;
                        socket?: { readyState?: number };
                    };
                };
            };
            const connection = connector.pusher?.connection;
            const connectionState = connection?.state;
            const socketReadyState = connection?.socket?.readyState;
            const isSocketClosing =
                socketReadyState === 2 || socketReadyState === 3; // WebSocket CLOSING/CLOSED

            if (connectionState === 'connected') {
                echo.leave(channelName);
            }

            // Skip disconnect calls once the underlying WebSocket is already shutting down to avoid browser errors.
            if (connectionState !== 'disconnected' && !isSocketClosing) {
                echo.disconnect();
            }
        };
    }, [isAuthenticated, queryClient, state.token, state.user?.id]);

    return <>{children}</>;
}
