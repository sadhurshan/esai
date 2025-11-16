import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { SettingsApi, type NumberingRule as ApiNumberingRule, type NumberingSettings as ApiNumberingSettings } from '@/sdk';
import type { ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { NumberingRule, NumberingSettings } from '@/types/settings';

function mapRule(payload?: ApiNumberingRule): NumberingRule {
    return {
        prefix: payload?.prefix ?? '',
        sequenceLength: payload?.seqLen ?? 4,
        next: payload?.next ?? 1,
        reset: payload?.reset ?? 'never',
        sample: payload?.sample ?? null,
    };
}

export function mapNumberingSettings(payload: ApiNumberingSettings): NumberingSettings {
    return {
        rfq: mapRule(payload.rfq),
        quote: mapRule(payload.quote),
        po: mapRule(payload.po),
        invoice: mapRule(payload.invoice),
        grn: mapRule(payload.grn),
        credit: mapRule(payload.credit),
    };
}

export function useNumberingSettings(): UseQueryResult<NumberingSettings, ApiError> {
    const settingsApi = useSdkClient(SettingsApi);

    return useQuery<NumberingSettings, ApiError>({
        queryKey: queryKeys.settings.numbering(),
        queryFn: async () => {
            const response = await settingsApi.showNumberingSettings();
            return mapNumberingSettings(response.data);
        },
        staleTime: 5 * 60 * 1000,
    });
}
