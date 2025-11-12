import { Toaster } from '@/components/ui/toaster';
import { type PropsWithChildren } from 'react';
import { ApiClientProvider } from '@/contexts/api-client-context';
import { AuthProvider } from '@/contexts/auth-context';

export function AppProviders({ children }: PropsWithChildren) {
    return (
        <AuthProvider>
            <ApiClientProvider>
                {children}
                <Toaster />
            </ApiClientProvider>
        </AuthProvider>
    );
}
