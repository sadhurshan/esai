import { Toaster } from '@/components/ui/toaster';
import { useCallback, useRef, type PropsWithChildren } from 'react';
import { ApiClientProvider } from '@/contexts/api-client-context';
import { AuthProvider } from '@/contexts/auth-context';
import { NotificationStreamProvider } from '@/providers/notification-stream-provider';
import type { QueryClient } from '@tanstack/react-query';

export function AppProviders({ children }: PropsWithChildren) {
    const queryClientRef = useRef<QueryClient | null>(null);

    const handleQueryClientReady = useCallback((client: QueryClient | null) => {
        queryClientRef.current = client;
    }, []);

    const handlePersonaChange = useCallback(() => {
        const client = queryClientRef.current;
        if (!client) {
            return;
        }

        client.cancelQueries();
        client.clear();
    }, []);

    return (
        <AuthProvider onPersonaChange={handlePersonaChange}>
            <ApiClientProvider onQueryClientReady={handleQueryClientReady}>
                <NotificationStreamProvider>
                    {children}
                    <Toaster />
                </NotificationStreamProvider>
            </ApiClientProvider>
        </AuthProvider>
    );
}
