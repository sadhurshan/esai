import { zodResolver } from '@hookform/resolvers/zod';
import { useEffect, useMemo } from 'react';
import { Helmet } from 'react-helmet-async';
import { useForm, useWatch } from 'react-hook-form';
import { z } from 'zod';

import { CurrencyPreferences } from '@/components/settings/currency-preferences';
import {
    UomMapper,
    type UomMappingRow,
} from '@/components/settings/uom-mapper';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Form,
    FormControl,
    FormField,
    FormItem,
    FormLabel,
    FormMessage,
} from '@/components/ui/form';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import {
    useLocalizationSettings,
    useUpdateLocalizationSettings,
} from '@/hooks/api/settings';
import { useUoms } from '@/hooks/api/use-uoms';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import type { LocalizationSettings } from '@/types/settings';

const localeOptions = [
    { value: 'en-US', label: 'English (United States)' },
    { value: 'en-GB', label: 'English (United Kingdom)' },
    { value: 'fr-FR', label: 'French (France)' },
    { value: 'de-DE', label: 'German (Germany)' },
    { value: 'ja-JP', label: 'Japanese (Japan)' },
    { value: 'zh-CN', label: 'Chinese (Simplified)' },
];

const dateFormatOptions = [
    { value: 'YYYY-MM-DD', label: 'YYYY-MM-DD (ISO)' },
    { value: 'DD/MM/YYYY', label: 'DD/MM/YYYY' },
    { value: 'MM/DD/YYYY', label: 'MM/DD/YYYY' },
];

const numberFormatOptions = [
    { value: '1,234.56', label: '1,234.56 · comma thousands, dot decimals' },
    { value: '1.234,56', label: '1.234,56 · dot thousands, comma decimals' },
    { value: '1 234,56', label: '1 234,56 · space thousands, comma decimals' },
];

const currencyOptions = [
    { value: 'USD', label: 'USD · US Dollar' },
    { value: 'EUR', label: 'EUR · Euro' },
    { value: 'GBP', label: 'GBP · British Pound' },
    { value: 'JPY', label: 'JPY · Japanese Yen' },
    { value: 'SGD', label: 'SGD · Singapore Dollar' },
];

const uomValueSchema = z
    .string()
    .optional()
    .nullable()
    .transform((value) => value ?? '');

const uomRowSchema = z.object({
    id: z.string(),
    from: uomValueSchema,
    to: uomValueSchema,
});

const localizationSchema = z.object({
    timezone: z.string().min(1, 'Select a timezone.'),
    locale: z.string().min(1, 'Select a locale.'),
    dateFormat: z.enum(['YYYY-MM-DD', 'DD/MM/YYYY', 'MM/DD/YYYY']),
    numberFormat: z.enum(['1,234.56', '1.234,56', '1 234,56']),
    currency: z.object({
        primary: z.string().min(1, 'Select a currency.'),
        displayFx: z.boolean().default(false),
    }),
    uom: z.object({
        baseUom: z.string().min(1, 'Select a base unit.'),
        mappings: z.array(uomRowSchema),
    }),
});

export type LocalizationFormValues = z.infer<typeof localizationSchema>;

function getTimezoneOptions(): { value: string; label: string }[] {
    if (typeof Intl !== 'undefined' && 'supportedValuesOf' in Intl) {
        try {
            const values = (
                Intl as unknown as {
                    supportedValuesOf: (type: string) => string[];
                }
            ).supportedValuesOf('timeZone');
            return values
                .map((value) => ({ value, label: value }))
                .slice(0, 250);
        } catch (error) {
            void error;
        }
    }

    return [
        'UTC',
        'America/New_York',
        'Europe/London',
        'Europe/Berlin',
        'Asia/Singapore',
        'Asia/Tokyo',
        'Australia/Sydney',
    ].map((value) => ({ value, label: value }));
}

const fallbackUoms = [
    { value: 'EA', label: 'EA · Each' },
    { value: 'KG', label: 'KG · Kilogram' },
    { value: 'LB', label: 'LB · Pound' },
    { value: 'L', label: 'L · Liter' },
];

function createRowId(seed?: string) {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }
    return `${seed ?? 'row'}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function mapRecordToRows(record: Record<string, string>): UomMappingRow[] {
    return Object.entries(record).map(([from, to]) => ({
        id: createRowId(`${from}-${to}`),
        from,
        to,
    }));
}

function rowsToRecord(rows: UomMappingRow[]): Record<string, string> {
    const output: Record<string, string> = {};
    rows.forEach((row) => {
        if (!row.from || !row.to) {
            return;
        }
        output[row.from.trim().toUpperCase()] = row.to;
    });
    return output;
}

function toFormValues(settings?: LocalizationSettings): LocalizationFormValues {
    return {
        timezone: settings?.timezone ?? 'UTC',
        locale: settings?.locale ?? 'en-US',
        dateFormat:
            (settings?.dateFormat as LocalizationFormValues['dateFormat']) ??
            'YYYY-MM-DD',
        numberFormat:
            (settings?.numberFormat as LocalizationFormValues['numberFormat']) ??
            '1,234.56',
        currency: {
            primary: settings?.currency.primary ?? 'USD',
            displayFx: settings?.currency.displayFx ?? false,
        },
        uom: {
            baseUom: settings?.uom.baseUom ?? 'EA',
            mappings: settings?.uom.maps
                ? mapRecordToRows(settings.uom.maps)
                : [],
        },
    } satisfies LocalizationFormValues;
}

export function LocalizationSettingsPage() {
    const { isAdmin } = useAuth();
    const localizationQuery = useLocalizationSettings();
    const updateLocalization = useUpdateLocalizationSettings();
    const uomQuery = useUoms({ enabled: isAdmin });

    const form = useForm<LocalizationFormValues>({
        resolver: zodResolver(localizationSchema),
        defaultValues: toFormValues(localizationQuery.data),
    });

    useEffect(() => {
        if (localizationQuery.data) {
            form.reset(toFormValues(localizationQuery.data));
        }
    }, [localizationQuery.data, form]);

    const timezoneOptions = useMemo(() => getTimezoneOptions(), []);
    const uomOptions = useMemo(() => {
        if (uomQuery.data && uomQuery.data.length > 0) {
            return uomQuery.data.map((option) => ({
                value: option.code,
                label: `${option.code} · ${option.name}`,
            }));
        }
        return fallbackUoms;
    }, [uomQuery.data]);

    const watchedValues = (useWatch<LocalizationFormValues>({
        control: form.control,
    }) ?? form.getValues()) as LocalizationFormValues;
    const baseUomValue =
        (useWatch({ control: form.control, name: 'uom.baseUom' }) as
            | string
            | undefined) ?? watchedValues.uom.baseUom;

    const preview = useMemo(
        () => buildLocalizationPreview(watchedValues),
        [watchedValues],
    );

    const handleSubmit = form.handleSubmit(async (values) => {
        try {
            await updateLocalization.mutateAsync({
                timezone: values.timezone,
                locale: values.locale,
                dateFormat: values.dateFormat,
                numberFormat: values.numberFormat,
                currency: values.currency,
                uom: {
                    baseUom: values.uom.baseUom,
                    maps: rowsToRecord(values.uom.mappings ?? []),
                },
            });
            publishToast({
                variant: 'success',
                title: 'Localization saved',
                description:
                    'Dates, numbers, and units now align with your workspace preferences.',
            });
        } catch (error) {
            void error;
            publishToast({
                variant: 'destructive',
                title: 'Unable to save localization',
                description: 'Please verify the configuration and try again.',
            });
        }
    });

    if (!isAdmin) {
        return <AccessDeniedPage />;
    }

    const isLoading = localizationQuery.isLoading && !localizationQuery.data;

    return (
        <div className="space-y-6">
            <Helmet>
                <title>Localization settings · Elements Supply</title>
            </Helmet>
            <div>
                <p className="text-sm text-muted-foreground">
                    Workspace · Settings
                </p>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Localization & units
                </h1>
                <p className="text-sm text-muted-foreground">
                    Configure locale, timezone, display formats, and base units
                    so every document renders consistently for buyers and
                    suppliers.
                </p>
            </div>
            {isLoading ? (
                <Skeleton className="h-96 w-full" />
            ) : (
                <Form {...form}>
                    <form
                        onSubmit={handleSubmit}
                        className="grid gap-6 lg:grid-cols-[2fr_1fr]"
                    >
                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Locale & formatting</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <FormField
                                            control={form.control}
                                            name="timezone"
                                            render={({ field }) => (
                                                <FormItem>
                                                    <FormLabel>
                                                        Timezone
                                                    </FormLabel>
                                                    <Select
                                                        value={field.value}
                                                        onValueChange={
                                                            field.onChange
                                                        }
                                                    >
                                                        <FormControl>
                                                            <SelectTrigger>
                                                                <SelectValue placeholder="Select timezone" />
                                                            </SelectTrigger>
                                                        </FormControl>
                                                        <SelectContent>
                                                            <SelectGroup>
                                                                {timezoneOptions.map(
                                                                    (
                                                                        option,
                                                                    ) => (
                                                                        <SelectItem
                                                                            key={
                                                                                option.value
                                                                            }
                                                                            value={
                                                                                option.value
                                                                            }
                                                                        >
                                                                            {
                                                                                option.label
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectGroup>
                                                        </SelectContent>
                                                    </Select>
                                                    <FormMessage />
                                                </FormItem>
                                            )}
                                        />
                                        <FormField
                                            control={form.control}
                                            name="locale"
                                            render={({ field }) => (
                                                <FormItem>
                                                    <FormLabel>
                                                        Locale
                                                    </FormLabel>
                                                    <Select
                                                        value={field.value}
                                                        onValueChange={
                                                            field.onChange
                                                        }
                                                    >
                                                        <FormControl>
                                                            <SelectTrigger>
                                                                <SelectValue placeholder="Select locale" />
                                                            </SelectTrigger>
                                                        </FormControl>
                                                        <SelectContent>
                                                            <SelectGroup>
                                                                {localeOptions.map(
                                                                    (
                                                                        option,
                                                                    ) => (
                                                                        <SelectItem
                                                                            key={
                                                                                option.value
                                                                            }
                                                                            value={
                                                                                option.value
                                                                            }
                                                                        >
                                                                            {
                                                                                option.label
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectGroup>
                                                        </SelectContent>
                                                    </Select>
                                                    <FormMessage />
                                                </FormItem>
                                            )}
                                        />
                                    </div>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <FormField
                                            control={form.control}
                                            name="dateFormat"
                                            render={({ field }) => (
                                                <FormItem>
                                                    <FormLabel>
                                                        Date format
                                                    </FormLabel>
                                                    <Select
                                                        value={field.value}
                                                        onValueChange={
                                                            field.onChange
                                                        }
                                                    >
                                                        <FormControl>
                                                            <SelectTrigger>
                                                                <SelectValue placeholder="Select format" />
                                                            </SelectTrigger>
                                                        </FormControl>
                                                        <SelectContent>
                                                            <SelectGroup>
                                                                {dateFormatOptions.map(
                                                                    (
                                                                        option,
                                                                    ) => (
                                                                        <SelectItem
                                                                            key={
                                                                                option.value
                                                                            }
                                                                            value={
                                                                                option.value
                                                                            }
                                                                        >
                                                                            {
                                                                                option.label
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectGroup>
                                                        </SelectContent>
                                                    </Select>
                                                    <FormMessage />
                                                </FormItem>
                                            )}
                                        />
                                        <FormField
                                            control={form.control}
                                            name="numberFormat"
                                            render={({ field }) => (
                                                <FormItem>
                                                    <FormLabel>
                                                        Number format
                                                    </FormLabel>
                                                    <Select
                                                        value={field.value}
                                                        onValueChange={
                                                            field.onChange
                                                        }
                                                    >
                                                        <FormControl>
                                                            <SelectTrigger>
                                                                <SelectValue placeholder="Select format" />
                                                            </SelectTrigger>
                                                        </FormControl>
                                                        <SelectContent>
                                                            <SelectGroup>
                                                                {numberFormatOptions.map(
                                                                    (
                                                                        option,
                                                                    ) => (
                                                                        <SelectItem
                                                                            key={
                                                                                option.value
                                                                            }
                                                                            value={
                                                                                option.value
                                                                            }
                                                                        >
                                                                            {
                                                                                option.label
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectGroup>
                                                        </SelectContent>
                                                    </Select>
                                                    <FormMessage />
                                                </FormItem>
                                            )}
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Currency & FX</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <CurrencyPreferences
                                        control={form.control}
                                        name="currency"
                                        options={currencyOptions}
                                    />
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Units of measure</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <FormField
                                        control={form.control}
                                        name="uom.baseUom"
                                        render={({ field }) => (
                                            <FormItem>
                                                <FormLabel>Base unit</FormLabel>
                                                <Select
                                                    value={field.value}
                                                    onValueChange={
                                                        field.onChange
                                                    }
                                                >
                                                    <FormControl>
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select base unit" />
                                                        </SelectTrigger>
                                                    </FormControl>
                                                    <SelectContent>
                                                        <SelectGroup>
                                                            {uomOptions.map(
                                                                (option) => (
                                                                    <SelectItem
                                                                        key={
                                                                            option.value
                                                                        }
                                                                        value={
                                                                            option.value
                                                                        }
                                                                    >
                                                                        {
                                                                            option.label
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectGroup>
                                                    </SelectContent>
                                                </Select>
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                    <FormField
                                        control={form.control}
                                        name="uom.mappings"
                                        render={({ field }) => (
                                            <FormItem>
                                                <UomMapper
                                                    value={field.value ?? []}
                                                    onChange={field.onChange}
                                                    baseUom={baseUomValue}
                                                    onBaseChange={(value) =>
                                                        form.setValue(
                                                            'uom.baseUom',
                                                            value,
                                                            {
                                                                shouldDirty: true,
                                                            },
                                                        )
                                                    }
                                                    options={uomOptions}
                                                />
                                                <FormMessage />
                                            </FormItem>
                                        )}
                                    />
                                </CardContent>
                                <CardFooter className="justify-end">
                                    <Button
                                        type="submit"
                                        disabled={updateLocalization.isPending}
                                    >
                                        {updateLocalization.isPending
                                            ? 'Saving…'
                                            : 'Save localization'}
                                    </Button>
                                </CardFooter>
                            </Card>
                        </div>
                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Preview</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4 text-sm">
                                    <div>
                                        <Label className="text-xs text-muted-foreground uppercase">
                                            Current time
                                        </Label>
                                        <p className="text-base font-medium">
                                            {preview.datetime}
                                        </p>
                                    </div>
                                    <div>
                                        <Label className="text-xs text-muted-foreground uppercase">
                                            Date format
                                        </Label>
                                        <p>{preview.date}</p>
                                    </div>
                                    <div>
                                        <Label className="text-xs text-muted-foreground uppercase">
                                            Number format
                                        </Label>
                                        <p>{preview.number}</p>
                                    </div>
                                    <div>
                                        <Label className="text-xs text-muted-foreground uppercase">
                                            Currency
                                        </Label>
                                        <p>{preview.currency}</p>
                                    </div>
                                    <div>
                                        <Label className="text-xs text-muted-foreground uppercase">
                                            FX tooltip
                                        </Label>
                                        <p>{preview.fxTooltip}</p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </form>
                </Form>
            )}
        </div>
    );
}

export function buildLocalizationPreview(values: LocalizationFormValues) {
    const sampleDate = new Date('2025-03-15T14:34:00Z');

    const datetimeFormatter = new Intl.DateTimeFormat(
        values.locale ?? 'en-US',
        {
            timeZone: values.timezone ?? 'UTC',
            dateStyle: 'full',
            timeStyle: 'short',
        },
    );

    const number = formatNumber(values.numberFormat ?? '1,234.56', 1234567.89);
    const date = formatDate(
        values.dateFormat ?? 'YYYY-MM-DD',
        sampleDate,
        values.timezone ?? 'UTC',
    );
    const currencyFormatter = new Intl.NumberFormat(values.locale ?? 'en-US', {
        style: 'currency',
        currency: values.currency.primary ?? 'USD',
    });

    return {
        datetime: datetimeFormatter.format(sampleDate),
        date,
        number,
        currency: currencyFormatter.format(12345.67),
        fxTooltip: values.currency.displayFx
            ? 'FX tooltip enabled · conversions visible to buyers'
            : 'FX tooltip disabled',
    };
}

function formatNumber(
    pattern: LocalizationFormValues['numberFormat'],
    value: number,
) {
    switch (pattern) {
        case '1.234,56':
            return value.toLocaleString('de-DE');
        case '1 234,56':
            return value.toLocaleString('fr-FR');
        default:
            return value.toLocaleString('en-US');
    }
}

function formatDate(
    pattern: LocalizationFormValues['dateFormat'],
    date: Date,
    timezone: string,
) {
    const zoned = new Date(
        date.toLocaleString('en-US', { timeZone: timezone }),
    );
    const year = zoned.getFullYear();
    const month = String(zoned.getMonth() + 1).padStart(2, '0');
    const day = String(zoned.getDate()).padStart(2, '0');

    switch (pattern) {
        case 'DD/MM/YYYY':
            return `${day}/${month}/${year}`;
        case 'MM/DD/YYYY':
            return `${month}/${day}/${year}`;
        default:
            return `${year}-${month}-${day}`;
    }
}
