import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { SettingsApi, type LocalizationSettings as ApiLocalizationSettings } from '@/sdk';
import type { ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { LocalizationSettings } from '@/types/settings';

export function mapLocalizationSettings(payload: ApiLocalizationSettings): LocalizationSettings {
    return {
        timezone: payload.timezone,
        locale: payload.locale,
        dateFormat: payload.dateFormat,
        numberFormat: payload.numberFormat,
        currency: {
            primary: payload.currency?.primary ?? 'USD',
            displayFx: Boolean(payload.currency?.displayFx),
        },
        uom: {
            baseUom: payload.uom?.baseUom ?? 'EA',
            maps: payload.uom?.maps ?? {},
        },
    };
}

export function useLocalizationSettings(): UseQueryResult<LocalizationSettings, ApiError> {
    const settingsApi = useSdkClient(SettingsApi);

    return useQuery<LocalizationSettings, ApiError>({
        queryKey: queryKeys.settings.localization(),
        queryFn: async () => {
            const response = await settingsApi.showLocalizationSettings();
            return mapLocalizationSettings(response.data);
        },
        staleTime: 5 * 60 * 1000,
    });
}
