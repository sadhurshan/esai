import { createContext, useContext, useMemo, type ReactNode } from 'react';

import { useLocalizationSettings } from '@/hooks/api/settings';
import type { LocalizationSettings } from '@/types/settings';
import { useAuth } from '@/contexts/auth-context';
import { isPlatformRole } from '@/constants/platform-roles';

const DEFAULT_SETTINGS: LocalizationSettings = {
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
        maps: {},
    },
};

const NUMBER_FORMAT_LOCALES: Record<string, string> = {
    '1,234.56': 'en-US',
    '1.234,56': 'de-DE',
    '1 234,56': 'fr-FR',
};

export type NumericValue = number | bigint | string | null | undefined;
export type DateValue = string | number | Date | null | undefined;

export type FormatNumberOptions = Intl.NumberFormatOptions & { fallback?: string };
export type FormatMoneyOptions = Intl.NumberFormatOptions & { currency?: string; fallback?: string };
export type FormatDateOptions = Omit<Intl.DateTimeFormatOptions, 'timeZone'> & { pattern?: string; fallback?: string };

export interface FormattingContextValue {
    locale: string;
    timezone: string;
    currency: string;
    displayFx: boolean;
    rawSettings: LocalizationSettings;
    formatNumber: (value: NumericValue, options?: FormatNumberOptions) => string;
    formatMoney: (value: NumericValue, options?: FormatMoneyOptions) => string;
    formatDate: (value: DateValue, options?: FormatDateOptions) => string;
}

const FormattingContext = createContext<FormattingContextValue | undefined>(undefined);

interface FormattingProviderProps {
    children: ReactNode;
    disableRemoteFetch?: boolean;
}

export function FormattingProvider({ children, disableRemoteFetch = false }: FormattingProviderProps) {
    const { state, activePersona } = useAuth();
    const role = state.user?.role ?? null;
    const isSupplierPersona = activePersona?.type === 'supplier';
    const allowRemoteFetch = !disableRemoteFetch && !isPlatformRole(role) && !isSupplierPersona;
    const localization = useLocalizationSettings({ enabled: allowRemoteFetch });

    const value = useMemo(() => buildFormattingContext(localization.data), [localization.data]);

    return <FormattingContext.Provider value={value}>{children}</FormattingContext.Provider>;
}

export function useFormatting(): FormattingContextValue {
    const context = useContext(FormattingContext);

    if (!context) {
        throw new Error('useFormatting must be used within a FormattingProvider');
    }

    return context;
}

function buildFormattingContext(settings?: LocalizationSettings): FormattingContextValue {
    const safe = settings ?? DEFAULT_SETTINGS;
    const locale = safe.locale || DEFAULT_SETTINGS.locale;
    const timezone = safe.timezone || DEFAULT_SETTINGS.timezone;
    const numberPattern = safe.numberFormat || DEFAULT_SETTINGS.numberFormat;
    const datePattern = safe.dateFormat || DEFAULT_SETTINGS.dateFormat;
    const numberLocale = deriveNumberLocale(numberPattern, locale);
    const currency = safe.currency?.primary || DEFAULT_SETTINGS.currency.primary;
    const displayFx = Boolean(safe.currency?.displayFx);

    return {
        locale,
        timezone,
        currency,
        displayFx,
        rawSettings: safe,
        formatNumber: (value, options) => formatNumberValue(value, numberLocale, options),
        formatMoney: (value, options) => formatMoneyValue(value, locale, currency, options),
        formatDate: (value, options) => formatDateValue(value, timezone, locale, datePattern, options),
    } satisfies FormattingContextValue;
}

function deriveNumberLocale(pattern?: string, fallback?: string) {
    if (!pattern) {
        return fallback ?? 'en-US';
    }

    const normalized = pattern.trim();
    return NUMBER_FORMAT_LOCALES[normalized] ?? fallback ?? 'en-US';
}

function formatNumberValue(value: NumericValue, locale: string, options?: FormatNumberOptions) {
    const { fallback, ...intlOptions } = options ?? {};
    const numeric = normalizeNumeric(value);

    if (numeric === null) {
        return fallback ?? '—';
    }

    return new Intl.NumberFormat(locale, {
        maximumFractionDigits: 2,
        ...intlOptions,
    }).format(numeric);
}

function formatMoneyValue(
    value: NumericValue,
    locale: string,
    defaultCurrency: string,
    options?: FormatMoneyOptions,
) {
    const { currency, fallback, ...intlOptions } = options ?? {};
    const numeric = normalizeNumeric(value);

    if (numeric === null) {
        return fallback ?? '—';
    }

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: currency ?? defaultCurrency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
        ...intlOptions,
    }).format(numeric);
}

function formatDateValue(
    value: DateValue,
    timezone: string,
    locale: string,
    defaultPattern: string,
    options?: FormatDateOptions,
) {
    const { fallback, pattern, ...intlOptions } = options ?? {};
    const date = normalizeDate(value);

    if (!date) {
        return fallback ?? '—';
    }
    const shouldUsePattern = !intlOptions.dateStyle && !intlOptions.timeStyle;

    if (pattern || shouldUsePattern) {
        return formatDateWithPattern(date, pattern ?? defaultPattern, timezone, locale);
    }

    return new Intl.DateTimeFormat(locale, { timeZone: timezone, ...intlOptions }).format(date);
}

function normalizeNumeric(value: NumericValue): number | bigint | null {
    if (value === null || value === undefined) {
        return null;
    }

    if (typeof value === 'number') {
        return Number.isNaN(value) ? null : value;
    }

    if (typeof value === 'bigint') {
        return value;
    }

    if (typeof value === 'string') {
        const trimmed = value.trim();
        if (!trimmed) {
            return null;
        }
        const parsed = Number(trimmed);
        return Number.isNaN(parsed) ? null : parsed;
    }

    return null;
}

function normalizeDate(value: DateValue): Date | null {
    if (!value) {
        return null;
    }

    const date = value instanceof Date ? value : new Date(value);

    return Number.isNaN(date.getTime()) ? null : date;
}

function formatDateWithPattern(date: Date, pattern: string, timezone: string, locale: string) {
    const zoned = convertToTimezone(date, timezone);
    const year = zoned.getFullYear();
    const month = String(zoned.getMonth() + 1).padStart(2, '0');
    const day = String(zoned.getDate()).padStart(2, '0');

    switch (pattern) {
        case 'DD/MM/YYYY':
            return `${day}/${month}/${year}`;
        case 'MM/DD/YYYY':
            return `${month}/${day}/${year}`;
        case 'YYYY-MM-DD':
            return `${year}-${month}-${day}`;
        default:
            return new Intl.DateTimeFormat(locale, { timeZone: timezone, dateStyle: 'medium' }).format(zoned);
    }
}

function convertToTimezone(date: Date, timezone: string) {
    try {
        const parts = date.toLocaleString('en-US', {
            timeZone: timezone,
        });

        return new Date(parts);
    } catch (error) {
        void error;
        return date;
    }
}
