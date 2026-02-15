import {
    useMutation,
    useQuery,
    useQueryClient,
    type UseMutationResult,
    type UseQueryResult,
} from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { CompanyAiSettings } from '@/types/settings';

interface ApiCompanyAiSettings {
    llm_answers_enabled: boolean;
    llm_provider: string;
}

export interface UpdateCompanyAiSettingsInput {
    llmAnswersEnabled: boolean;
}

function mapCompanyAiSettingsPayload(
    payload: ApiCompanyAiSettings,
): CompanyAiSettings {
    const enabled = Boolean(payload.llm_answers_enabled);
    const provider = payload.llm_provider === 'openai' ? 'openai' : 'dummy';

    return {
        llmAnswersEnabled: enabled,
        llmProvider: provider,
    };
}

export function useCompanyAiSettings(): UseQueryResult<
    CompanyAiSettings,
    ApiError
> {
    return useQuery<CompanyAiSettings, ApiError>({
        queryKey: queryKeys.settings.ai(),
        queryFn: async () => {
            const response = (await api.get(
                '/settings/ai',
            )) as ApiCompanyAiSettings;
            return mapCompanyAiSettingsPayload(response);
        },
        staleTime: 2 * 60 * 1000,
    });
}

export function useUpdateCompanyAiSettings(): UseMutationResult<
    CompanyAiSettings,
    ApiError,
    UpdateCompanyAiSettingsInput
> {
    const queryClient = useQueryClient();

    return useMutation<
        CompanyAiSettings,
        ApiError,
        UpdateCompanyAiSettingsInput
    >({
        mutationFn: async (input) => {
            const response = (await api.patch('/settings/ai', {
                llm_answers_enabled: input.llmAnswersEnabled,
            })) as ApiCompanyAiSettings;

            return mapCompanyAiSettingsPayload(response);
        },
        onSuccess: (settings) => {
            queryClient.setQueryData(queryKeys.settings.ai(), settings);
        },
    });
}
