import type { MoneySettings } from '@/sdk';

const resolveLocale = () => {
    if (typeof navigator !== 'undefined' && navigator.language) {
        return navigator.language;
    }

    if (typeof Intl !== 'undefined' && Intl.DateTimeFormat) {
        return Intl.DateTimeFormat().resolvedOptions().locale;
    }

    return 'en-US';
};

export const formatCurrencyMinor = (amountMinor?: number | null, currency?: string | null, locale?: string) => {
    if (amountMinor === undefined || amountMinor === null) {
        return '—';
    }

    const formatter = new Intl.NumberFormat(locale ?? resolveLocale(), {
        style: 'currency',
        currency: currency ?? 'USD',
        minimumFractionDigits: 2,
    });

    return formatter.format(amountMinor / 100);
};

export interface CurrencyOption {
    value: string;
    label: string;
}

const DEFAULT_CURRENCY_OPTION: CurrencyOption = {
    value: 'USD',
    label: 'USD · United States Dollar',
};

type CurrencySettingsSubset = Pick<MoneySettings, 'baseCurrency' | 'pricingCurrency'> | null | undefined;

export function buildCurrencyOptions(settings?: CurrencySettingsSubset): CurrencyOption[] {
    const options = new Map<string, string>();

    const addCurrency = (code?: string, name?: string, fallbackName?: string) => {
        if (!code) {
            return;
        }

        if (!options.has(code)) {
            const labelSuffix = name ?? fallbackName ?? 'Currency';
            options.set(code, `${code} · ${labelSuffix}`);
        }
    };

    addCurrency(settings?.pricingCurrency?.code, settings?.pricingCurrency?.name, 'Pricing currency');
    addCurrency(settings?.baseCurrency?.code, settings?.baseCurrency?.name, 'Base currency');

    if (options.size === 0) {
        return [DEFAULT_CURRENCY_OPTION];
    }

    return Array.from(options.entries()).map(([value, label]) => ({ value, label }));
}

export function getDefaultCurrency(options: CurrencyOption[], fallback = DEFAULT_CURRENCY_OPTION.value): string {
    return options[0]?.value ?? fallback;
}
