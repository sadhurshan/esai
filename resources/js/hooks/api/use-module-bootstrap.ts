import { useSdkClient } from '@/contexts/api-client-context';
import { HealthApi } from '@/sdk';
import { useQuery } from '@tanstack/react-query';

/**
 * Temporary bootstrap hook to keep module stubs wired to the API until dedicated endpoints are exposed.
 * TODO: replace with module-specific queries once the corresponding SDK operations are implemented.
 */
export function useModuleBootstrap(moduleKey: string) {
    const client = useSdkClient(HealthApi);

    return useQuery({
        queryKey: ['app', 'module-bootstrap', moduleKey],
        queryFn: async () => {
            const response = await client.getHealth();
            return response.data;
        },
    });
}
