import { describe, expect, it } from 'vitest';

import {
    buildLocalizationPreview,
    type LocalizationFormValues,
} from '../localization-settings-page';

function createValues(
    overrides: Partial<LocalizationFormValues> = {},
): LocalizationFormValues {
    return {
        timezone: 'UTC',
        locale: 'en-US',
        dateFormat: 'YYYY-MM-DD',
        numberFormat: '1,234.56',
        currency: {
            primary: 'USD',
            displayFx: false,
        },
        uom: {
            baseUom: 'EA',
            mappings: [],
        },
        ...overrides,
    } as LocalizationFormValues;
}

describe('buildLocalizationPreview', () => {
    it('renders consistent preview for default locale', () => {
        const preview = buildLocalizationPreview(createValues());
        expect(preview).toMatchSnapshot();
    });

    it('reflects locale, timezone, and FX toggle differences', () => {
        const preview = buildLocalizationPreview(
            createValues({
                timezone: 'Europe/Berlin',
                locale: 'de-DE',
                dateFormat: 'DD/MM/YYYY',
                numberFormat: '1.234,56',
                currency: {
                    primary: 'EUR',
                    displayFx: true,
                },
            }),
        );

        expect(preview).toMatchSnapshot();
    });
});
