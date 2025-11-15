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
