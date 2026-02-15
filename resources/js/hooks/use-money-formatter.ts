import { useCallback, useMemo } from 'react';

import { useMoneySettings } from '@/hooks/api/use-money-settings';

const FALLBACK_CURRENCY = 'USD';
const FALLBACK_MINOR_UNIT = 2;

export function useMoneyFormatter() {
    const { data: settings } = useMoneySettings();

    const currencyCode =
        settings?.pricingCurrency?.code ??
        settings?.baseCurrency?.code ??
        FALLBACK_CURRENCY;
    const minorUnit =
        settings?.pricingCurrency?.minorUnit ??
        settings?.baseCurrency?.minorUnit ??
        FALLBACK_MINOR_UNIT;
    const locale =
        typeof navigator !== 'undefined' ? navigator.language : 'en-US';

    const formatter = useMemo(() => {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: currencyCode,
            minimumFractionDigits: minorUnit,
            maximumFractionDigits: minorUnit,
        });
    }, [currencyCode, locale, minorUnit]);

    return useCallback(
        (value?: number | null) => {
            if (value === null || value === undefined || Number.isNaN(value)) {
                return '—';
            }

            try {
                return formatter.format(value);
            } catch (error) {
                void error;
                return `${value.toFixed(minorUnit)} ${currencyCode}`;
            }
        },
        [currencyCode, formatter, minorUnit],
    );
}
