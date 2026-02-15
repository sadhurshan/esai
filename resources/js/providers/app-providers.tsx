import { Toaster } from '@/components/ui/toaster';
import { CopilotChatWidget } from '@/components/ai/CopilotChatWidget';
import { ApiClientProvider } from '@/contexts/api-client-context';
import { AuthProvider } from '@/contexts/auth-context';
import { CopilotWidgetProvider } from '@/contexts/copilot-widget-context';
import { NotificationStreamProvider } from '@/providers/notification-stream-provider';
import type { QueryClient } from '@tanstack/react-query';
import { useCallback, useEffect, useRef, type PropsWithChildren } from 'react';

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

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        (window as Window & { __appProvidersMounted?: boolean })
            .__appProvidersMounted = true;
    }, []);

    return (
        <AuthProvider onPersonaChange={handlePersonaChange}>
            <ApiClientProvider onQueryClientReady={handleQueryClientReady}>
                <CopilotWidgetProvider>
                    <NotificationStreamProvider>
                        {children}
                        <CopilotChatWidget />
                        <Toaster />
                    </NotificationStreamProvider>
                </CopilotWidgetProvider>
            </ApiClientProvider>
        </AuthProvider>
    );
}
