import { useMutation, useQueryClient, type UseMutationResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { SettingsApi, type CompanySettings as ApiCompanySettings } from '@/sdk';
import type { ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { CompanySettings } from '@/types/settings';
import { mapCompanySettings } from './use-company';

export type UpdateCompanySettingsInput = CompanySettings;

export function buildCompanySettingsPayload(input: UpdateCompanySettingsInput): ApiCompanySettings {
    const payload = {
        legalName: input.legalName,
        displayName: input.displayName,
        taxId: input.taxId ?? null,
        registrationNumber: input.registrationNumber ?? null,
        emails: input.emails ?? [],
        phones: input.phones ?? [],
        billTo: {
            attention: input.billTo?.attention ?? null,
            line1: input.billTo?.line1 ?? '',
            line2: input.billTo?.line2 ?? null,
            city: input.billTo?.city ?? null,
            state: input.billTo?.state ?? null,
            postalCode: input.billTo?.postalCode ?? null,
            country: input.billTo?.country ?? '',
        },
        shipFrom: {
            attention: input.shipFrom?.attention ?? null,
            line1: input.shipFrom?.line1 ?? '',
            line2: input.shipFrom?.line2 ?? null,
            city: input.shipFrom?.city ?? null,
            state: input.shipFrom?.state ?? null,
            postalCode: input.shipFrom?.postalCode ?? null,
            country: input.shipFrom?.country ?? '',
        },
        logoUrl: input.logoUrl ?? null,
        markUrl: input.markUrl ?? null,
    } satisfies Record<string, unknown>;

    return payload as ApiCompanySettings;
}

export function useUpdateCompanySettings(): UseMutationResult<CompanySettings, ApiError, UpdateCompanySettingsInput> {
    const queryClient = useQueryClient();
    const settingsApi = useSdkClient(SettingsApi);

    return useMutation<CompanySettings, ApiError, UpdateCompanySettingsInput>({
        mutationFn: async (input) => {
            const response = await settingsApi.updateCompanySettings({
                companySettings: buildCompanySettingsPayload(input),
            });

            return mapCompanySettings(response.data);
        },
        onSuccess: (settings) => {
            queryClient.setQueryData(queryKeys.settings.company(), settings);
        },
    });
}
