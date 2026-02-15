import {
    useMutation,
    useQueryClient,
    type UseMutationResult,
} from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import {
    CompanySettingsFromJSON,
    CompanySettingsToJSON,
    type CompanySettings as ApiCompanySettings,
} from '@/sdk';
import type { CompanySettings } from '@/types/settings';
import { mapCompanySettings } from './use-company';

export interface UpdateCompanySettingsInput extends CompanySettings {
    logoFile?: File | null;
    markFile?: File | null;
}

export function buildCompanySettingsPayload(
    input: UpdateCompanySettingsInput,
): ApiCompanySettings {
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
        logoUrl: input.logoFile ? null : (input.logoUrl ?? null),
        markUrl: input.markFile ? null : (input.markUrl ?? null),
    } satisfies Record<string, unknown>;

    return payload as ApiCompanySettings;
}

export function buildCompanySettingsFormData(
    input: UpdateCompanySettingsInput,
): FormData {
    const jsonPayload = CompanySettingsToJSON(
        buildCompanySettingsPayload(input),
    );
    const formData = new FormData();

    appendFormData(formData, jsonPayload);
    formData.append('_method', 'PATCH');

    if (input.logoFile instanceof File) {
        formData.append('logo', input.logoFile);
    }

    if (input.markFile instanceof File) {
        formData.append('mark', input.markFile);
    }

    return formData;
}

export function useUpdateCompanySettings(): UseMutationResult<
    CompanySettings,
    ApiError,
    UpdateCompanySettingsInput
> {
    const queryClient = useQueryClient();

    return useMutation<CompanySettings, ApiError, UpdateCompanySettingsInput>({
        mutationFn: async (input) => {
            const data = (await api.post(
                '/settings/company',
                buildCompanySettingsFormData(input),
            )) as unknown;
            const payload = CompanySettingsFromJSON(data);

            return mapCompanySettings(payload);
        },
        onSuccess: (settings) => {
            queryClient.setQueryData(queryKeys.settings.company(), settings);
        },
    });
}

const appendFormData = (
    formData: FormData,
    value: unknown,
    parentKey?: string,
): void => {
    if (value === undefined) {
        return;
    }

    if (value === null) {
        if (parentKey) {
            formData.append(parentKey, '');
        }

        return;
    }

    if (Array.isArray(value)) {
        value.forEach((entry) =>
            appendFormData(formData, entry, `${parentKey}[]`),
        );
        return;
    }

    if (typeof value === 'object') {
        Object.entries(value as Record<string, unknown>).forEach(
            ([key, entry]) => {
                const nextKey = parentKey ? `${parentKey}[${key}]` : key;
                appendFormData(formData, entry, nextKey);
            },
        );

        return;
    }

    if (parentKey) {
        formData.append(parentKey, String(value));
    }
};
