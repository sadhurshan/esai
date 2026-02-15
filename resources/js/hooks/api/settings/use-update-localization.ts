import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import type { ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import {
    SettingsApi,
    type LocalizationSettings as ApiLocalizationSettings,
} from '@/sdk';
import type { LocalizationSettings } from '@/types/settings';
import { mapLocalizationSettings } from './use-localization';

export type UpdateLocalizationSettingsInput = LocalizationSettings;

export function buildLocalizationSettingsPayload(
    input: UpdateLocalizationSettingsInput,
): ApiLocalizationSettings {
    const payload = {
        timezone: input.timezone,
        locale: input.locale,
        dateFormat: input.dateFormat,
        numberFormat: input.numberFormat,
        currency: {
            primary: input.currency.primary,
            displayFx: Boolean(input.currency.displayFx),
        },
        uom: {
            baseUom: input.uom.baseUom,
            maps: input.uom.maps,
        },
    } satisfies ApiLocalizationSettings;

    return payload;
}

export function useUpdateLocalizationSettings(): UseMutationResult<
    LocalizationSettings,
    ApiError,
    UpdateLocalizationSettingsInput
> {
    const queryClient = useQueryClient();
    const settingsApi = useSdkClient(SettingsApi);

    return useMutation<
        LocalizationSettings,
        ApiError,
        UpdateLocalizationSettingsInput
    >({
        mutationFn: async (input) => {
            const response = await settingsApi.updateLocalizationSettings({
                localizationSettings: buildLocalizationSettingsPayload(input),
            });

            return mapLocalizationSettings(response.data);
        },
        onSuccess: (settings) => {
            queryClient.setQueryData(
                queryKeys.settings.localization(),
                settings,
            );
        },
    });
}
