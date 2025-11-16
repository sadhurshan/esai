import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { useSdkClient } from '@/contexts/api-client-context';
import { SettingsApi, type CompanyAddress as ApiCompanyAddress, type CompanySettings as ApiCompanySettings } from '@/sdk';
import type { ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type { CompanyAddress, CompanySettings } from '@/types/settings';

const defaultAddress: CompanyAddress = {
    line1: '',
    country: '',
};

function mapAddress(payload?: ApiCompanyAddress | null): CompanyAddress {
    if (!payload) {
        return { ...defaultAddress };
    }

    return {
        attention: payload.attention ?? null,
        line1: payload.line1 ?? '',
        line2: payload.line2 ?? null,
        city: payload.city ?? null,
        state: payload.state ?? null,
        postalCode: payload.postalCode ?? null,
        country: payload.country ?? '',
    };
}

export function mapCompanySettings(payload: ApiCompanySettings): CompanySettings {
    return {
        legalName: payload.legalName,
        displayName: payload.displayName,
        taxId: payload.taxId ?? null,
        registrationNumber: payload.registrationNumber ?? null,
        emails: payload.emails ?? [],
        phones: payload.phones ?? [],
        billTo: mapAddress(payload.billTo),
        shipFrom: mapAddress(payload.shipFrom),
        logoUrl: payload.logoUrl ?? null,
        markUrl: payload.markUrl ?? null,
    };
}

export function useCompanySettings(): UseQueryResult<CompanySettings, ApiError> {
    const settingsApi = useSdkClient(SettingsApi);

    return useQuery<CompanySettings, ApiError>({
        queryKey: queryKeys.settings.company(),
        queryFn: async () => {
            const response = await settingsApi.showCompanySettings();
            return mapCompanySettings(response.data);
        },
        staleTime: 5 * 60 * 1000,
    });
}
