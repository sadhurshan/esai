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
    type NumberingRule as ApiNumberingRule,
    type NumberingSettings as ApiNumberingSettings,
} from '@/sdk';
import type { NumberingRule, NumberingSettings } from '@/types/settings';
import { mapNumberingSettings } from './use-numbering';

const DOC_KEYS: Array<keyof NumberingSettings> = [
    'rfq',
    'quote',
    'po',
    'invoice',
    'grn',
    'credit',
];

export type UpdateNumberingSettingsInput = NumberingSettings;

function serializeRule(rule: NumberingRule): ApiNumberingRule {
    const payload: ApiNumberingRule = {
        prefix: rule.prefix,
        seqLen: rule.sequenceLength,
        next: rule.next,
        reset: rule.reset,
        sample: rule.sample ?? undefined,
    };

    return payload;
}

export function buildNumberingSettingsPayload(
    input: UpdateNumberingSettingsInput,
): ApiNumberingSettings {
    return DOC_KEYS.reduce<ApiNumberingSettings>((acc, key) => {
        acc[key] = serializeRule(input[key]);
        return acc;
    }, {} as ApiNumberingSettings);
}

export function useUpdateNumberingSettings(): UseMutationResult<
    NumberingSettings,
    ApiError,
    UpdateNumberingSettingsInput
> {
    const queryClient = useQueryClient();
    const settingsApi = useSdkClient(SettingsApi);

    return useMutation<
        NumberingSettings,
        ApiError,
        UpdateNumberingSettingsInput
    >({
        mutationFn: async (input) => {
            const response = await settingsApi.updateNumberingSettings({
                numberingSettings: buildNumberingSettingsPayload(input),
            });

            return mapNumberingSettings(response.data);
        },
        onSuccess: (settings) => {
            queryClient.setQueryData(queryKeys.settings.numbering(), settings);
        },
    });
}
