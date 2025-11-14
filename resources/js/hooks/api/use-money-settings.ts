import { useQuery } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { queryKeys } from '@/lib/queryKeys';
import { MoneyApi, type MoneySettings } from '@/sdk';

export function useMoneySettings() {
    const moneyApi = useSdkClient(MoneyApi);

    return useQuery<MoneySettings>({
        queryKey: queryKeys.money.settings(),
        queryFn: async () => {
            const response = await moneyApi.showMoneySettings();
            return response.data;
        },
        staleTime: 5 * 60 * 1000,
    });
}
