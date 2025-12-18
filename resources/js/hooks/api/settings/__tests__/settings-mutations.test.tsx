import { renderHook } from '@testing-library/react';
import { waitFor } from '@testing-library/dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { PropsWithChildren } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { buildCompanySettingsPayload, useUpdateCompanySettings } from '../use-update-company';
import { buildLocalizationSettingsPayload, useUpdateLocalizationSettings } from '../use-update-localization';
import { buildNumberingSettingsPayload, useUpdateNumberingSettings } from '../use-update-numbering';
import { mapCompanySettings } from '../use-company';
import { mapLocalizationSettings } from '../use-localization';
import { mapNumberingSettings } from '../use-numbering';
import type { LocalizationSettings, NumberingSettings } from '@/types/settings';
import type { UpdateCompanySettingsInput } from '../use-update-company';
import { queryKeys } from '@/lib/queryKeys';
import { useSdkClient } from '@/contexts/api-client-context';
import { api } from '@/lib/api';
import { CompanySettingsFromJSON } from '@/sdk';

vi.mock('@/contexts/api-client-context', () => ({
    useSdkClient: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
    api: {
        patch: vi.fn(),
    },
}));

const useSdkClientMock = vi.mocked(useSdkClient);
const apiPatchMock = vi.mocked(api.patch);

const companyInput: UpdateCompanySettingsInput = {
    legalName: 'Acme Corporation',
    displayName: 'Acme',
    taxId: '99-1111111',
    registrationNumber: 'ACME-001',
    emails: ['ops@acme.example'],
    phones: ['+1 555 0100'],
    billTo: {
        attention: 'AP',
        line1: '1 Market St',
        city: 'San Francisco',
        state: 'CA',
        postalCode: '94105',
        country: 'US',
    },
    shipFrom: {
        line1: '800 Warehouse Rd',
        city: 'Oakland',
        state: 'CA',
        postalCode: '94607',
        country: 'US',
    },
    logoUrl: 'https://cdn.example.com/logo.svg',
    markUrl: null,
};

const localizationInput: LocalizationSettings = {
    timezone: 'America/Chicago',
    locale: 'en-US',
    dateFormat: 'MM/DD/YYYY',
    numberFormat: '1,234.56',
    currency: {
        primary: 'USD',
        displayFx: true,
    },
    uom: {
        baseUom: 'EA',
        maps: {
            PK: 'EA',
        },
    },
};

const numberingInput: NumberingSettings = {
    rfq: { prefix: 'RFQ-', sequenceLength: 4, next: 25, reset: 'never' },
    quote: { prefix: 'Q-', sequenceLength: 4, next: 7, reset: 'never' },
    po: { prefix: 'PO-', sequenceLength: 5, next: 9001, reset: 'yearly' },
    invoice: { prefix: 'INV-', sequenceLength: 6, next: 155, reset: 'never' },
    grn: { prefix: 'GRN-', sequenceLength: 4, next: 88, reset: 'never' },
    credit: { prefix: 'CR-', sequenceLength: 5, next: 12, reset: 'yearly' },
};

function createWrapper() {
    const queryClient = new QueryClient({
        defaultOptions: {
            queries: {
                retry: false,
            },
        },
    });

    function Wrapper({ children }: PropsWithChildren) {
        return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
    }

    return { Wrapper, queryClient };
}

describe('settings mutation helpers', () => {
    it('buildCompanySettingsPayload serializes addresses and contacts', () => {
        const payload = buildCompanySettingsPayload(companyInput);

        expect(payload).toMatchObject({
            legalName: 'Acme Corporation',
            displayName: 'Acme',
            taxId: '99-1111111',
            registrationNumber: 'ACME-001',
            emails: ['ops@acme.example'],
            phones: ['+1 555 0100'],
            billTo: {
                attention: 'AP',
                line1: '1 Market St',
                country: 'US',
            },
            shipFrom: {
                line1: '800 Warehouse Rd',
                country: 'US',
            },
        });
    });

    it('buildLocalizationSettingsPayload preserves currency + UoM preferences', () => {
        const payload = buildLocalizationSettingsPayload(localizationInput);

        expect(payload).toEqual({
            timezone: 'America/Chicago',
            locale: 'en-US',
            dateFormat: 'MM/DD/YYYY',
            numberFormat: '1,234.56',
            currency: {
                primary: 'USD',
                displayFx: true,
            },
            uom: {
                baseUom: 'EA',
                maps: {
                    PK: 'EA',
                },
            },
        });
    });

    it('buildNumberingSettingsPayload maps internal keys to API format', () => {
        const payload = buildNumberingSettingsPayload(numberingInput);

        expect(payload.po).toMatchObject({
            prefix: 'PO-',
            seqLen: 5,
            next: 9001,
            reset: 'yearly',
        });
        expect(payload.invoice.seqLen).toBe(6);
    });
});

describe('settings mutation hooks', () => {
    const updateCompanySettings = vi.fn();
    const updateLocalizationSettings = vi.fn();
    const updateNumberingSettings = vi.fn();

    beforeEach(() => {
        apiPatchMock.mockReset();
        updateCompanySettings.mockReset();
        updateLocalizationSettings.mockReset();
        updateNumberingSettings.mockReset();

        useSdkClientMock.mockReturnValue({
            updateCompanySettings,
            updateLocalizationSettings,
            updateNumberingSettings,
        } as never);
    });

    afterEach(() => {
        vi.clearAllMocks();
    });

    it('useUpdateCompanySettings uploads multipart payloads and caches results', async () => {
        const apiResponse = {
            legal_name: 'Acme Corporation',
            display_name: 'Acme',
            tax_id: '99-1111111',
            registration_number: 'ACME-001',
            emails: ['ops@acme.example'],
            phones: ['+1 555 0100'],
            bill_to: {
                line1: '1 Market St',
                country: 'US',
            },
            ship_from: {
                line1: '800 Warehouse Rd',
                country: 'US',
            },
        } satisfies Record<string, unknown>;

        apiPatchMock.mockResolvedValue(apiResponse);

        const { Wrapper, queryClient } = createWrapper();
        const { result } = renderHook(() => useUpdateCompanySettings(), { wrapper: Wrapper });

        await result.current.mutateAsync(companyInput);

        expect(apiPatchMock).toHaveBeenCalledTimes(1);
        const [url, formData] = apiPatchMock.mock.calls[0];

        expect(url).toBe('/settings/company');
        expect(formData).toBeInstanceOf(FormData);
        const data = formData as FormData;
        expect(data.get('legal_name')).toBe('Acme Corporation');
        expect(data.getAll('emails[]')).toEqual(['ops@acme.example']);
        expect(data.get('bill_to[line1]')).toBe('1 Market St');

        await waitFor(() => {
            expect(queryClient.getQueryData(queryKeys.settings.company())).toEqual(
                mapCompanySettings(CompanySettingsFromJSON(apiResponse as never)),
            );
        });
    });

    it('useUpdateLocalizationSettings syncs preferences', async () => {
        const apiResponse = {
            timezone: 'America/Chicago',
            locale: 'en-US',
            dateFormat: 'MM/DD/YYYY',
            numberFormat: '1,234.56',
            currency: { primary: 'USD', displayFx: true },
            uom: { baseUom: 'EA', maps: { PK: 'EA' } },
        } satisfies Record<string, unknown>;

        updateLocalizationSettings.mockResolvedValue({ data: apiResponse });

        const { Wrapper, queryClient } = createWrapper();
        const { result } = renderHook(() => useUpdateLocalizationSettings(), { wrapper: Wrapper });

        await result.current.mutateAsync(localizationInput);

        expect(updateLocalizationSettings).toHaveBeenCalledWith({
            localizationSettings: buildLocalizationSettingsPayload(localizationInput),
        });

        await waitFor(() => {
            expect(queryClient.getQueryData(queryKeys.settings.localization())).toEqual(
                mapLocalizationSettings(apiResponse as never),
            );
        });
    });

    it('useUpdateNumberingSettings updates document rules', async () => {
        const apiResponse = {
            rfq: { prefix: 'RFQ-', seqLen: 4, next: 25, reset: 'never' },
            quote: { prefix: 'Q-', seqLen: 4, next: 7, reset: 'never' },
            po: { prefix: 'PO-', seqLen: 5, next: 9001, reset: 'yearly' },
            invoice: { prefix: 'INV-', seqLen: 6, next: 155, reset: 'never' },
            grn: { prefix: 'GRN-', seqLen: 4, next: 88, reset: 'never' },
            credit: { prefix: 'CR-', seqLen: 5, next: 12, reset: 'yearly' },
        } satisfies Record<string, unknown>;

        updateNumberingSettings.mockResolvedValue({ data: apiResponse });

        const { Wrapper, queryClient } = createWrapper();
        const { result } = renderHook(() => useUpdateNumberingSettings(), { wrapper: Wrapper });

        await result.current.mutateAsync(numberingInput);

        expect(updateNumberingSettings).toHaveBeenCalledWith({
            numberingSettings: buildNumberingSettingsPayload(numberingInput),
        });

        await waitFor(() => {
            expect(queryClient.getQueryData(queryKeys.settings.numbering())).toEqual(
                mapNumberingSettings(apiResponse as never),
            );
        });
    });
});
