import { useEffect, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { useForm, useFieldArray, useWatch, type Control, type FieldArray, type FieldPath, type UseFieldArrayReturn } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Form } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { publishToast } from '@/components/ui/use-toast';
import { AddressEditor } from '@/components/settings/address-editor';
import { useAuth } from '@/contexts/auth-context';
import { useCompanySettings, useUpdateCompanySettings, type UpdateCompanySettingsInput } from '@/hooks/api/settings';
import type { CompanyAddress } from '@/types/settings';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';

const MAX_BRAND_ASSET_SIZE = 4 * 1024 * 1024; // 4 MB
const ACCEPTED_BRAND_FILE_TYPES = 'image/png,image/jpeg,image/webp,image/svg+xml';

const isFile = (value: unknown): value is File => typeof File !== 'undefined' && value instanceof File;

const brandFileSchema = z
    .custom<File>((value) => (value === undefined || value === null ? true : isFile(value)), {
        message: 'Upload must be an image file.',
    })
    .refine((value) => !value || value.size <= MAX_BRAND_ASSET_SIZE, 'Brand asset must be 4 MB or smaller.');

const addressSchema = z.object({
    attention: z.string().optional().nullable(),
    line1: z.string().min(1, 'Address line is required.'),
    line2: z.string().optional().nullable(),
    city: z.string().optional().nullable(),
    state: z.string().optional().nullable(),
    postalCode: z.string().optional().nullable(),
    country: z.string().min(2, 'Country is required.'),
});

const contactSchema = z.object({
    value: z.string().min(1, 'Enter a value.'),
});

const companySchema = z.object({
    legalName: z.string().min(1, 'Legal name is required.'),
    displayName: z.string().min(1, 'Display name is required.'),
    registrationNumber: z.string().optional().nullable(),
    taxId: z.string().optional().nullable(),
    emails: z.array(contactSchema).min(1, 'Add at least one email.'),
    phones: z.array(contactSchema).min(1, 'Add at least one phone number.'),
    billTo: addressSchema,
    shipFrom: addressSchema,
    logoUrl: z.string().url('Enter a valid URL.').optional().or(z.literal('')).nullable(),
    markUrl: z.string().url('Enter a valid URL.').optional().or(z.literal('')).nullable(),
    logoFile: brandFileSchema.optional().nullable(),
    markFile: brandFileSchema.optional().nullable(),
});

const emptyAddress: CompanyAddress = {
    attention: '',
    line1: '',
    line2: '',
    city: '',
    state: '',
    postalCode: '',
    country: '',
};

type CompanyFormValues = z.infer<typeof companySchema>;

function toFormValues(settings?: CompanySettings): CompanyFormValues {
    return {
        legalName: settings?.legalName ?? '',
        displayName: settings?.displayName ?? '',
        registrationNumber: settings?.registrationNumber ?? '',
        taxId: settings?.taxId ?? '',
        emails: (settings?.emails?.length ? settings.emails : ['']).map((value) => ({ value })),
        phones: (settings?.phones?.length ? settings.phones : ['']).map((value) => ({ value })),
        billTo: { ...emptyAddress, ...settings?.billTo },
        shipFrom: { ...emptyAddress, ...settings?.shipFrom },
        logoUrl: settings?.logoUrl ?? '',
        markUrl: settings?.markUrl ?? '',
        logoFile: null,
        markFile: null,
    } satisfies CompanyFormValues;
}

function sanitizeContacts(values: Array<{ value?: string | null }>): string[] {
    return values
    .map((entry) => entry.value?.trim() ?? '')
        .filter((value, index, arr) => value.length > 0 && arr.indexOf(value) === index);
}

function sanitizeAddress(address: CompanyAddress): CompanyAddress {
    return {
        attention: address.attention?.trim() || '',
        line1: address.line1?.trim() ?? '',
        line2: address.line2?.trim() || '',
        city: address.city?.trim() || '',
        state: address.state?.trim() || '',
        postalCode: address.postalCode?.trim() || '',
        country: address.country?.trim() ?? '',
    };
}

function buildCompanyPayload(values: CompanyFormValues): UpdateCompanySettingsInput {
    const trimmedLogo = values.logoUrl?.trim() ?? '';
    const trimmedMark = values.markUrl?.trim() ?? '';

    return {
        legalName: values.legalName.trim(),
        displayName: values.displayName.trim(),
        registrationNumber: values.registrationNumber?.trim() || null,
        taxId: values.taxId?.trim() || null,
        emails: sanitizeContacts(values.emails),
        phones: sanitizeContacts(values.phones),
        billTo: sanitizeAddress(values.billTo),
        shipFrom: sanitizeAddress(values.shipFrom),
        logoUrl: values.logoFile ? null : trimmedLogo || null,
        markUrl: values.markFile ? null : trimmedMark || null,
        logoFile: values.logoFile ?? null,
        markFile: values.markFile ?? null,
    } satisfies UpdateCompanySettingsInput;
}

export function CompanySettingsPage() {
    const { isAdmin } = useAuth();
    const companyQuery = useCompanySettings();
    const updateCompany = useUpdateCompanySettings();

    const form = useForm<CompanyFormValues>({
        resolver: zodResolver(companySchema),
        mode: 'onBlur',
        defaultValues: toFormValues(companyQuery.data),
    });

    const emailFields = useFieldArray({ control: form.control, name: 'emails' });
    const phoneFields = useFieldArray({ control: form.control, name: 'phones' });

    useEffect(() => {
        if (companyQuery.data) {
            form.reset(toFormValues(companyQuery.data));
        }
    }, [companyQuery.data, form]);

    const logoUrl = useWatch({ control: form.control, name: 'logoUrl' }) ?? '';
    const markUrl = useWatch({ control: form.control, name: 'markUrl' }) ?? '';
    const logoFile = useWatch({ control: form.control, name: 'logoFile' });
    const markFile = useWatch({ control: form.control, name: 'markFile' });

    const [logoFilePreview, setLogoFilePreview] = useState<string | null>(null);
    const [markFilePreview, setMarkFilePreview] = useState<string | null>(null);

    useEffect(() => {
        if (!logoFile || !isFile(logoFile)) {
            setLogoFilePreview(null);

            return;
        }

        const objectUrl = URL.createObjectURL(logoFile);
        setLogoFilePreview(objectUrl);

        return () => URL.revokeObjectURL(objectUrl);
    }, [logoFile]);

    useEffect(() => {
        if (!markFile || !isFile(markFile)) {
            setMarkFilePreview(null);

            return;
        }

        const objectUrl = URL.createObjectURL(markFile);
        setMarkFilePreview(objectUrl);

        return () => URL.revokeObjectURL(objectUrl);
    }, [markFile]);

    const handleSubmit = form.handleSubmit(async (values) => {
        try {
            await updateCompany.mutateAsync(buildCompanyPayload(values));
            publishToast({
                variant: 'success',
                title: 'Company profile saved',
                description: 'Workspace branding and addresses now reflect the latest information.',
            });
        } catch (error) {
            void error;
            publishToast({
                variant: 'destructive',
                title: 'Unable to save company profile',
                description: 'Please review the highlighted fields and try again.',
            });
        }
    });

    const handleRemoveLogo = (): void => {
        form.setValue('logoFile', null, { shouldDirty: true });
        form.setValue('logoUrl', '', { shouldDirty: true });
    };

    const handleRemoveMark = (): void => {
        form.setValue('markFile', null, { shouldDirty: true });
        form.setValue('markUrl', '', { shouldDirty: true });
    };

    const logoPreview = logoFilePreview ?? (logoUrl?.length ? logoUrl : '');
    const markPreview = markFilePreview ?? (markUrl?.length ? markUrl : '');
    const canRemoveLogo = Boolean(logoFile || logoUrl);
    const canRemoveMark = Boolean(markFile || markUrl);

    const watchedValues = (useWatch<CompanyFormValues>({ control: form.control }) ?? form.getValues()) as CompanyFormValues;

    const preview = useMemo(() => {
        return {
            displayName: watchedValues.displayName,
            legalName: watchedValues.legalName,
            emails: sanitizeContacts(watchedValues.emails ?? []),
            phones: sanitizeContacts(watchedValues.phones ?? []),
            billTo: watchedValues.billTo ?? emptyAddress,
            shipFrom: watchedValues.shipFrom ?? emptyAddress,
        };
    }, [watchedValues]);

    if (!isAdmin) {
        return <AccessDeniedPage />;
    }

    const isLoading = companyQuery.isLoading && !companyQuery.data;

    return (
        <div className="space-y-6">
            <Helmet>
                <title>Company settings · Elements Supply</title>
            </Helmet>
            <div>
                <p className="text-sm text-muted-foreground">Workspace · Settings</p>
                <h1 className="text-2xl font-semibold tracking-tight">Company profile</h1>
                <p className="text-sm text-muted-foreground">
                    Update legal identity, billing addresses, and brand assets used across RFQs, POs, invoices, and PDFs.
                </p>
            </div>
            {isLoading ? (
                <Skeleton className="h-96 w-full" />
            ) : (
                <Form {...form}>
                    <form onSubmit={handleSubmit} className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Identity</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <FormField
                                            control={form.control}
                                            name="legalName"
                                            render={({ field }) => (
                                                <FormItem>
                                                    <FormLabel>Legal name</FormLabel>
                                                    <FormControl>
                                                        <Input placeholder="Elements Supply Inc." {...field} />
                                                    </FormControl>
                                                    <FormMessage />
                                                </FormItem>
                                            )}
                                        />
                                        <FormField
                                            control={form.control}
                                            name="displayName"
                                            render={({ field }) => (
                                                <FormItem>
                                                    <FormLabel>Display name</FormLabel>
                                                    <FormControl>
                                                        <Input placeholder="Elements Supply" {...field} />
                                                    </FormControl>
                                                    <FormMessage />
                                                </FormItem>
                                            )}
                                        />
                                    </div>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <FormField
                                            control={form.control}
                                            name="registrationNumber"
                                            render={({ field }) => (
                                                <FormItem>
                                                    <FormLabel>Registration number</FormLabel>
                                                    <FormControl>
                                                        <Input
                                                            placeholder="123456789"
                                                            name={field.name}
                                                            ref={field.ref}
                                                            onBlur={field.onBlur}
                                                            onChange={field.onChange}
                                                            value={field.value ?? ''}
                                                        />
                                                    </FormControl>
                                                    <FormMessage />
                                                </FormItem>
                                            )}
                                        />
                                        <FormField
                                            control={form.control}
                                            name="taxId"
                                            render={({ field }) => (
                                                <FormItem>
                                                    <FormLabel>Tax ID</FormLabel>
                                                    <FormControl>
                                                        <Input
                                                            placeholder="US-99-9999999"
                                                            autoComplete="off"
                                                            name={field.name}
                                                            ref={field.ref}
                                                            onBlur={field.onBlur}
                                                            onChange={field.onChange}
                                                            value={field.value ?? ''}
                                                        />
                                                    </FormControl>
                                                    <FormMessage />
                                                </FormItem>
                                            )}
                                        />
                                    </div>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="space-y-4 rounded-lg border p-4">
                                            <FormField
                                                control={form.control}
                                                name="logoUrl"
                                                render={({ field }) => <input type="hidden" {...field} value={field.value ?? ''} />}
                                            />
                                            <FormField
                                                control={form.control}
                                                name="logoFile"
                                                render={({ field }) => (
                                                    <FormItem>
                                                        <FormLabel>Primary logo</FormLabel>
                                                        <FormControl>
                                                            <Input
                                                                type="file"
                                                                name={field.name}
                                                                accept={ACCEPTED_BRAND_FILE_TYPES}
                                                                onChange={(event) => {
                                                                    const file = event.target.files?.[0] ?? null;
                                                                    field.onChange(file);
                                                                    event.target.value = '';
                                                                }}
                                                                onBlur={field.onBlur}
                                                                ref={field.ref}
                                                            />
                                                        </FormControl>
                                                        <FormDescription>SVG, PNG, JPG, or WebP up to 4 MB.</FormDescription>
                                                        <FormMessage />
                                                    </FormItem>
                                                )}
                                            />
                                            <div className="flex flex-col items-center gap-2">
                                                <div className="flex h-24 w-full items-center justify-center rounded-lg border bg-muted/30">
                                                    {logoPreview ? (
                                                        <img src={logoPreview} alt="Primary logo preview" className="max-h-20" />
                                                    ) : (
                                                        <p className="text-xs text-muted-foreground">No logo uploaded</p>
                                                    )}
                                                </div>
                                                <Button type="button" variant="ghost" size="sm" disabled={!canRemoveLogo} onClick={handleRemoveLogo}>
                                                    Remove logo
                                                </Button>
                                            </div>
                                        </div>
                                        <div className="space-y-4 rounded-lg border p-4">
                                            <FormField
                                                control={form.control}
                                                name="markUrl"
                                                render={({ field }) => <input type="hidden" {...field} value={field.value ?? ''} />}
                                            />
                                            <FormField
                                                control={form.control}
                                                name="markFile"
                                                render={({ field }) => (
                                                    <FormItem>
                                                        <FormLabel>Symbol / mark</FormLabel>
                                                        <FormControl>
                                                            <Input
                                                                type="file"
                                                                name={field.name}
                                                                accept={ACCEPTED_BRAND_FILE_TYPES}
                                                                onChange={(event) => {
                                                                    const file = event.target.files?.[0] ?? null;
                                                                    field.onChange(file);
                                                                    event.target.value = '';
                                                                }}
                                                                onBlur={field.onBlur}
                                                                ref={field.ref}
                                                            />
                                                        </FormControl>
                                                        <FormDescription>Square version for compact placements (max 4 MB).</FormDescription>
                                                        <FormMessage />
                                                    </FormItem>
                                                )}
                                            />
                                            <div className="flex flex-col items-center gap-2">
                                                <div className="flex h-24 w-full items-center justify-center rounded-lg border bg-muted/30">
                                                    {markPreview ? (
                                                        <img src={markPreview} alt="Mark preview" className="max-h-20" />
                                                    ) : (
                                                        <p className="text-xs text-muted-foreground">No symbol uploaded</p>
                                                    )}
                                                </div>
                                                <Button type="button" variant="ghost" size="sm" disabled={!canRemoveMark} onClick={handleRemoveMark}>
                                                    Remove symbol
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Contacts</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    <ContactList
                                        control={form.control}
                                        name="emails"
                                        label="Notification emails"
                                        placeholder="ap@elements-supply.ai"
                                        fields={emailFields}
                                        type="email"
                                    />
                                    <ContactList
                                        control={form.control}
                                        name="phones"
                                        label="Phone numbers"
                                        placeholder="+1 555-0100"
                                        fields={phoneFields}
                                        type="tel"
                                    />
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Addresses</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    <AddressEditor control={form.control} name="billTo" title="Bill to" description="Printed on invoices and credit notes." />
                                    <AddressEditor
                                        control={form.control}
                                        name="shipFrom"
                                        title="Ship from"
                                        description="Defaults on RFQs, POs, and receiving paperwork."
                                    />
                                </CardContent>
                                <CardFooter className="justify-end">
                                    <Button type="submit" disabled={updateCompany.isPending}>
                                        {updateCompany.isPending ? 'Saving…' : 'Save company profile'}
                                    </Button>
                                </CardFooter>
                            </Card>
                        </div>
                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Preview</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div>
                                        <p className="text-2xl font-semibold">{preview.displayName || 'Display name'}</p>
                                        <p className="text-sm text-muted-foreground">{preview.legalName || 'Legal name'}</p>
                                    </div>
                                    <div>
                                        <Label className="text-xs uppercase text-muted-foreground">Emails</Label>
                                        <p className="text-sm">{preview.emails.join(', ') || '—'}</p>
                                    </div>
                                    <div>
                                        <Label className="text-xs uppercase text-muted-foreground">Phones</Label>
                                        <p className="text-sm">{preview.phones.join(', ') || '—'}</p>
                                    </div>
                                    <div>
                                        <Label className="text-xs uppercase text-muted-foreground">Bill to</Label>
                                        <AddressPreview address={preview.billTo} />
                                    </div>
                                    <div>
                                        <Label className="text-xs uppercase text-muted-foreground">Ship from</Label>
                                        <AddressPreview address={preview.shipFrom} />
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

interface ContactListProps<TName extends 'emails' | 'phones'> {
    control: Control<CompanyFormValues>;
    name: TName;
    label: string;
    placeholder: string;
    type: string;
    fields: UseFieldArrayReturn<CompanyFormValues, TName>;
}

function ContactList<TName extends 'emails' | 'phones'>({ control, name, label, placeholder, type, fields }: ContactListProps<TName>) {
    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <div>
                    <p className="font-medium text-sm">{label}</p>
                    <p className="text-xs text-muted-foreground">These appear on documents and outbound emails.</p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => fields.append({ value: '' } as FieldArray<CompanyFormValues, TName>)}
                >
                    Add
                </Button>
            </div>
            <div className="space-y-3">
                {fields.fields.map((field, index) => (
                    <FormField
                        key={field.id}
                        control={control}
                        name={`${name}.${index}.value` as FieldPath<CompanyFormValues>}
                        render={({ field: formField }) => (
                            <FormItem>
                                <FormLabel className="sr-only">{label}</FormLabel>
                                <div className="flex gap-2">
                                    <FormControl>
                                            <Input
                                                type={type}
                                                placeholder={placeholder}
                                                name={formField.name}
                                                ref={formField.ref}
                                                onBlur={formField.onBlur}
                                                onChange={formField.onChange}
                                                value={typeof formField.value === 'string' ? formField.value : ''}
                                            />
                                    </FormControl>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => fields.remove(index)}
                                        disabled={fields.fields.length <= 1}
                                    >
                                        Remove
                                    </Button>
                                </div>
                                <FormMessage />
                            </FormItem>
                        )}
                    />
                ))}
            </div>
        </div>
    );
}

function AddressPreview({ address }: { address?: Partial<CompanyAddress> | null }) {
    if (!address || Object.values(address).every((value) => !value)) {
        return <p className="text-sm text-muted-foreground">Not set</p>;
    }

    return (
        <div className="text-sm">
            {address.attention ? <p>{address.attention}</p> : null}
            {address.line1 ? <p>{address.line1}</p> : null}
            {address.line2 ? <p>{address.line2}</p> : null}
            <p>
                {[address.city, address.state, address.postalCode].filter((part) => part && part.length > 0).join(', ')}
            </p>
            <p>{address.country}</p>
        </div>
    );
}
