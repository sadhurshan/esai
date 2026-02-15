import { useSdkClient } from '@/contexts/api-client-context';
import { HealthApi } from '@/sdk';
import { useQuery } from '@tanstack/react-query';

export function useWorkspaceHealth() {
    const client = useSdkClient(HealthApi);

    return useQuery({
        queryKey: ['app', 'health'],
        queryFn: async () => {
            const response = await client.getHealth();
            return response.data;
        },
    });
}
