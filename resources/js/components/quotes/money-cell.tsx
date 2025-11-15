import { Info } from 'lucide-react';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { useMoneyFormatter } from '@/hooks/use-money-formatter';
import { useMoneySettings } from '@/hooks/api/use-money-settings';
import { useMemo } from 'react';

interface MoneyCellProps {
    amountMinor?: number | null;
    currency?: string | null;
    convertedAmountMinor?: number | null;
    convertedCurrency?: string | null;
    label?: string;
    className?: string;
}

const DEFAULT_MINOR_UNIT = 2;

function toMajorUnits(valueMinor?: number | null, minorUnit?: number | null): number | null {
    if (valueMinor === null || valueMinor === undefined) {
        return null;
    }

    const divisor = Math.pow(10, minorUnit ?? DEFAULT_MINOR_UNIT);
    return valueMinor / divisor;
}

function formatForeignCurrency(amountMinor?: number | null, currency?: string | null, locale?: string): string | null {
    if (amountMinor == null || !currency) {
        return null;
    }

    try {
        const formatter = new Intl.NumberFormat(locale ?? 'en-US', {
            style: 'currency',
            currency,
            minimumFractionDigits: DEFAULT_MINOR_UNIT,
            maximumFractionDigits: DEFAULT_MINOR_UNIT,
        });
        return formatter.format(toMajorUnits(amountMinor, DEFAULT_MINOR_UNIT) ?? 0);
    } catch (error) {
        void error;
        return `${(toMajorUnits(amountMinor, DEFAULT_MINOR_UNIT) ?? 0).toFixed(DEFAULT_MINOR_UNIT)} ${currency}`;
    }
}

export function MoneyCell({
    amountMinor,
    currency,
    convertedAmountMinor,
    convertedCurrency,
    label = 'Total',
    className,
}: MoneyCellProps) {
    const formatCompanyMoney = useMoneyFormatter();
    const { data: moneySettings } = useMoneySettings();

    const companyMinorUnit = useMemo(() => {
        return moneySettings?.pricingCurrency?.minorUnit ?? moneySettings?.baseCurrency?.minorUnit ?? DEFAULT_MINOR_UNIT;
    }, [moneySettings]);

    const companyCurrency = useMemo(() => {
        return moneySettings?.pricingCurrency?.code ?? moneySettings?.baseCurrency?.code ?? convertedCurrency ?? currency ?? 'USD';
    }, [convertedCurrency, currency, moneySettings]);

    const displayValueMinor =
        convertedAmountMinor !== undefined && convertedAmountMinor !== null
            ? convertedAmountMinor
            : amountMinor;

    const companyValue = toMajorUnits(displayValueMinor, companyMinorUnit);
    const formattedCompanyValue = companyValue !== null ? formatCompanyMoney(companyValue) : '—';

    const showFxTooltip = Boolean(
        currency && currency !== companyCurrency && amountMinor !== undefined && amountMinor !== null,
    );

    const originalPresentation = useMemo(() => {
        if (!showFxTooltip) {
            return null;
        }

        return formatForeignCurrency(amountMinor, currency);
    }, [amountMinor, currency, showFxTooltip]);

    return (
        <div className={cn('flex flex-col gap-1 text-sm text-foreground', className)}>
            <div className="flex items-center gap-1 font-semibold">
                <span>{formattedCompanyValue}</span>
                {showFxTooltip && originalPresentation ? (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <button
                                type="button"
                                className="text-muted-foreground transition hover:text-foreground"
                                aria-label="View original currency value"
                            >
                                <Info className="h-4 w-4" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <div className="space-y-1">
                                <p className="font-semibold">Supplier currency</p>
                                <p>{originalPresentation}</p>
                                <p className="text-xs text-muted-foreground">
                                    {/* TODO: surface FX rate + timestamp once exposed by Money settings. */}
                                    Converted to {companyCurrency} for workspace comparisons.
                                </p>
                            </div>
                        </TooltipContent>
                    </Tooltip>
                ) : null}
            </div>
            <span className="text-xs uppercase tracking-wide text-muted-foreground">{label}</span>
        </div>
    );
}
