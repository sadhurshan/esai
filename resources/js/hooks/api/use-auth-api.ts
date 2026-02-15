import { createConfiguration } from '@/sdk';
import { AuthApi } from '@/sdk/auth-client';
import { useMemo } from 'react';

export function useAuthApi(token?: string | null) {
    const baseUrl = useMemo(
        () => (import.meta.env.VITE_API_BASE_URL ?? '/api').replace(/\/$/, ''),
        [],
    );

    return useMemo(() => {
        return new AuthApi(
            createConfiguration({
                baseUrl,
                bearerToken: token ? () => token ?? undefined : undefined,
                defaultHeaders: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }),
        );
    }, [baseUrl, token]);
}
