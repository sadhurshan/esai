import { useQuery } from '@tanstack/react-query';
import { HealthApi } from '@/sdk';
import { useSdkClient } from '@/contexts/api-client-context';

export function useWorkspaceHealth() {
    const client = useSdkClient(HealthApi);

    return useQuery({
        queryKey: ['workspace', 'health'],
        queryFn: async () => {
            const response = await client.getHealth();
            return response.data;
        },
    });
}
