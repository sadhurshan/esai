import { useMemo, useState, type ComponentType } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Building2, ShieldCheck, Clock, AlertCircle, Loader2 } from 'lucide-react';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useAuth } from '@/contexts/auth-context';
import { publishToast } from '@/components/ui/use-toast';
import { useApplyForSupplier, type SupplierApplicationPayload } from '@/hooks/api/useSupplierSelfService';

const optionalTextField = (max: number, message?: string) =>
    z.string().max(max, message ?? `Maximum ${max} characters.`).optional().or(z.literal(''));

const listField = z.string().max(500, 'Keep this under 500 characters.').optional().or(z.literal(''));
const optionalEmail = z.union([z.literal(''), z.string().email('Enter a valid email address.')]);
const optionalUrl = z.union([z.literal(''), z.string().url('Enter a valid URL (https://example.com).')]);
const optionalCountry = z.union([z.literal(''), z.string().length(2, 'Use a 2-letter country code.')]);

const positiveInteger = z
    .preprocess((value) => {
        if (value === '' || value === null || value === undefined) {
            return undefined;
        }
        if (typeof value === 'number') {
            return Number.isFinite(value) ? value : undefined;
        }
        if (typeof value === 'string') {
            const trimmed = value.trim();
            if (trimmed === '') {
                return undefined;
            }
            return Number(trimmed);
        }
        return value;
    }, z.number({ invalid_type_error: 'Enter a valid whole number.' }).int('Use whole numbers only.').min(1, 'Must be at least 1.'))
    .optional();

const supplierApplicationSchema = z
    .object({
        description: optionalTextField(500),
        website: optionalUrl,
        address: optionalTextField(191),
        city: optionalTextField(160),
        country: optionalCountry,
        moq: positiveInteger,
        minOrderQty: positiveInteger,
        leadTimeDays: positiveInteger,
        capabilitiesMethods: listField,
        capabilitiesMaterials: listField,
        capabilitiesFinishes: listField,
        capabilitiesTolerances: listField,
        capabilitiesIndustries: listField,
        certifications: listField,
        contactName: optionalTextField(160),
        contactEmail: optionalEmail,
        contactPhone: optionalTextField(60),
        notes: optionalTextField(255),
        facilities: optionalTextField(500),
    })
    .superRefine((values, ctx) => {
        const capabilityInputs = [
            values.capabilitiesMethods,
            values.capabilitiesMaterials,
            values.capabilitiesFinishes,
            values.capabilitiesTolerances,
            values.capabilitiesIndustries,
        ];
        const hasCapabilities = capabilityInputs.some((value) => Boolean(value && value.trim().length > 0));

        if (!hasCapabilities) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                path: ['capabilitiesMethods'],
                message: 'Add at least one capability or industry focus.',
            });
        }

        const hasContact =
            Boolean(values.contactEmail && values.contactEmail.trim().length > 0) ||
            Boolean(values.contactPhone && values.contactPhone.trim().length > 0) ||
            Boolean(values.contactName && values.contactName.trim().length > 0);

        if (!hasContact) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                path: ['contactEmail'],
                message: 'Provide a contact email, phone, or name for follow-ups.',
            });
        }
    });

export type SupplierApplicationFormValues = z.infer<typeof supplierApplicationSchema>;

type SupplierStatus = 'none' | 'pending' | 'approved' | 'rejected' | 'suspended';

const STATUS_META: Record<
    SupplierStatus,
    { label: string; description: string; icon: ComponentType<{ className?: string }>; badge: 'default' | 'secondary' | 'outline' | 'destructive' }
> = {
    none: {
        label: 'Not applied',
        description: 'Your company has not requested supplier access yet.',
        icon: Building2,
        badge: 'secondary',
    },
    pending: {
        label: 'Pending review',
        description: 'Our team is reviewing your submission. Expect an update soon.',
        icon: Clock,
        badge: 'outline',
    },
    approved: {
        label: 'Approved supplier',
        description: 'Supplier tooling, invitations, and RFQ replies are enabled.',
        icon: ShieldCheck,
        badge: 'default',
    },
    rejected: {
        label: 'Application rejected',
        description: 'The last application was declined. Update your profile and reapply.',
        icon: AlertCircle,
        badge: 'destructive',
    },
    suspended: {
        label: 'Supplier suspended',
        description: 'Access is temporarily disabled. Contact support for next steps.',
        icon: AlertCircle,
        badge: 'destructive',
    },
};

function splitList(value?: string | null): string[] | undefined {
    if (!value) {
        return undefined;
    }

    const items = value
        .split(/[\n,]/)
        .map((item) => item.trim())
        .filter((item) => item.length > 0);

    return items.length > 0 ? items : undefined;
}

function buildCapabilities(values: SupplierApplicationFormValues) {
    const payload = {
        methods: splitList(values.capabilitiesMethods),
        materials: splitList(values.capabilitiesMaterials),
        finishes: splitList(values.capabilitiesFinishes),
        tolerances: splitList(values.capabilitiesTolerances),
        industries: splitList(values.capabilitiesIndustries),
    };

    return Object.entries(payload).reduce<SupplierApplicationPayload['capabilities']>((acc, [key, list]) => {
        if (list && list.length > 0) {
            acc[key as keyof SupplierApplicationPayload['capabilities']] = list;
        }
        return acc;
    }, {});
}

export function SupplierApplicationPanel() {
    const { state, refresh } = useAuth();
    const role = state.user?.role ?? null;
    const status = (state.company?.supplier_status ?? 'none') as SupplierStatus;
    const companyStatus = state.company?.status ?? 'pending';
    const isCompanyApproved = ['active', 'trial'].includes(companyStatus);
    const meta = STATUS_META[status] ?? STATUS_META.none;
    const [dialogOpen, setDialogOpen] = useState(false);
    const [formError, setFormError] = useState<string | null>(null);
    const applyMutation = useApplyForSupplier();
    const isOwner = role === 'owner';
    const canApply = isOwner && isCompanyApproved && (status === 'none' || status === 'rejected');
    const actionLabel = useMemo(() => {
        if (!isCompanyApproved) {
            return 'Awaiting company approval';
        }

        if (!isOwner) {
            return 'Owner access required';
        }

        switch (status) {
            case 'pending':
                return 'Application pending';
            case 'approved':
                return 'Supplier access enabled';
            case 'suspended':
                return 'Supplier access suspended';
            case 'rejected':
                return 'Reapply as supplier';
            default:
                return 'Apply as supplier';
        }
    }, [isCompanyApproved, isOwner, status]);

    const {
        register,
        handleSubmit,
        formState: { errors, isSubmitting },
        reset,
    } = useForm<SupplierApplicationFormValues>({
        resolver: zodResolver(supplierApplicationSchema),
        defaultValues: {
            description: '',
            website: '',
            address: '',
            city: '',
            country: '',
            moq: undefined,
            minOrderQty: undefined,
            leadTimeDays: undefined,
            capabilitiesMethods: '',
            capabilitiesMaterials: '',
            capabilitiesFinishes: '',
            capabilitiesTolerances: '',
            capabilitiesIndustries: '',
            certifications: '',
            contactName: '',
            contactEmail: '',
            contactPhone: '',
            notes: '',
            facilities: '',
        },
    });

    const onSubmit = handleSubmit(async (values) => {
        setFormError(null);
        const payload: SupplierApplicationPayload = {
            description: values.description || undefined,
            capabilities: buildCapabilities(values),
            address: values.address || undefined,
            city: values.city || undefined,
            country: values.country || undefined,
            moq: values.moq,
            min_order_qty: values.minOrderQty,
            lead_time_days: values.leadTimeDays,
            certifications: splitList(values.certifications),
            facilities: values.facilities || undefined,
            website: values.website || undefined,
            contact: {
                name: values.contactName || undefined,
                email: values.contactEmail || undefined,
                phone: values.contactPhone || undefined,
            },
            notes: values.notes || undefined,
        };

        try {
            await applyMutation.mutateAsync(payload);
            publishToast({
                variant: 'success',
                title: 'Application submitted',
                description: 'We will notify you once the supplier review is complete.',
            });
            setDialogOpen(false);
            reset();
            await refresh();
        } catch (error) {
            setFormError(error instanceof Error ? error.message : 'Unable to submit your supplier application.');
        }
    });

    const StatusIcon = meta.icon;
    const statusDescription = useMemo(() => {
        if (status === 'approved') {
            return 'You can now receive RFQs, quotes, and POs from buyers across the Elements Supply network.';
        }

        if (status === 'pending') {
            return 'Applications typically take 1-2 business days. We will email the owner once a decision is made.';
        }

        return meta.description;
    }, [meta.description, status]);

    return (
        <Card>
            <CardHeader className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <CardTitle className="text-lg">Supplier application</CardTitle>
                    <CardDescription>
                        Control when your company participates as a supplier. Owners can submit the profile below for review.
                    </CardDescription>
                </div>
                <Badge variant={meta.badge} className="flex items-center gap-1">
                    <StatusIcon className="h-3.5 w-3.5" />
                    {meta.label}
                </Badge>
            </CardHeader>
            <CardContent className="space-y-4">
                <p className="text-sm text-muted-foreground">{statusDescription}</p>

                {!isOwner ? (
                    <Alert>
                        <AlertDescription>
                            Only workspace owners can submit or re-submit supplier applications. Ask your owner to start the process if
                            you need supplier access.
                        </AlertDescription>
                    </Alert>
                ) : null}

                {!isCompanyApproved ? (
                    <Alert>
                        <AlertDescription>
                            Company verification is still pending. Platform operations must approve your documents before supplier tools
                            are unlocked.
                        </AlertDescription>
                    </Alert>
                ) : null}

                {status === 'approved' && isCompanyApproved ? (
                    <Alert>
                        <AlertDescription>
                            Supplier tooling is live. To stay listed in the supplier directory, keep your profile current and ensure your
                            compliance documents remain valid.
                        </AlertDescription>
                    </Alert>
                ) : null}

                {status === 'rejected' ? (
                    <Alert variant="destructive">
                        <AlertDescription>
                            The previous submission was rejected. Update your capabilities and certifications before applying again.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <Button onClick={() => (canApply ? setDialogOpen(true) : null)} disabled={!canApply}>
                    {actionLabel}
                </Button>

                <Dialog
                    open={dialogOpen}
                    onOpenChange={(next) => (!isSubmitting && !applyMutation.isPending ? setDialogOpen(next) : null)}
                >
                    <DialogContent className="max-h-[85vh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle>Supplier application</DialogTitle>
                            <DialogDescription>
                                Share your capabilities, certifications, and business readiness. Our platform team uses this to approve supplier
                                access.
                            </DialogDescription>
                        </DialogHeader>
                        <form className="space-y-5" onSubmit={onSubmit}>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="description">Overview</Label>
                                    <Textarea id="description" rows={4} placeholder="What do you manufacture?" {...register('description')} />
                                    {errors.description ? (
                                        <p className="text-xs text-destructive">{errors.description.message}</p>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">200-400 characters describing your services.</p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="website">Website</Label>
                                    <Input
                                        id="website"
                                        placeholder="https://example.com"
                                        {...register('website', {
                                            setValueAs: (value) => (typeof value === 'string' ? value.trim() : value),
                                        })}
                                    />
                                    {errors.website ? <p className="text-xs text-destructive">{errors.website.message}</p> : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="address">Address</Label>
                                    <Input id="address" placeholder="123 Industrial Way" {...register('address')} />
                                    {errors.address ? <p className="text-xs text-destructive">{errors.address.message}</p> : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="city">City</Label>
                                    <Input id="city" placeholder="Austin" {...register('city')} />
                                    {errors.city ? <p className="text-xs text-destructive">{errors.city.message}</p> : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="country">Country (ISO)</Label>
                                    <Input
                                        id="country"
                                        maxLength={2}
                                        placeholder="US"
                                        {...register('country', {
                                            setValueAs: (value) => (typeof value === 'string' ? value.trim().toUpperCase() : value),
                                        })}
                                    />
                                    {errors.country ? <p className="text-xs text-destructive">{errors.country.message}</p> : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="moq">MOQ</Label>
                                    <Input id="moq" type="number" min={1} placeholder="Minimum order quantity" {...register('moq')} />
                                    {errors.moq ? <p className="text-xs text-destructive">{errors.moq.message}</p> : null}
                                </div>
                                {/* <div className="space-y-2">
                                    <Label htmlFor="minOrderQty">Minimum order qty</Label>
                                    <Input id="minOrderQty" type="number" min={1} placeholder="e.g. 25" {...register('minOrderQty')} />
                                    {errors.minOrderQty ? <p className="text-xs text-destructive">{errors.minOrderQty.message}</p> : null}
                                </div> */}
                                <div className="space-y-2">
                                    <Label htmlFor="leadTimeDays">Lead time (days)</Label>
                                    <Input id="leadTimeDays" type="number" min={1} placeholder="e.g. 10" {...register('leadTimeDays')} />
                                    {errors.leadTimeDays ? <p className="text-xs text-destructive">{errors.leadTimeDays.message}</p> : null}
                                </div>
                            </div>

                            <div className="space-y-4">
                                <div>
                                    <Label className="text-sm font-medium">Capabilities</Label>
                                    <p className="text-xs text-muted-foreground">Comma or line separated lists.</p>
                                </div>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="capabilitiesMethods">Processes</Label>
                                        <Textarea id="capabilitiesMethods" rows={3} placeholder="CNC machining, stamping" {...register('capabilitiesMethods')} />
                                        {errors.capabilitiesMethods ? (
                                            <p className="text-xs text-destructive">{errors.capabilitiesMethods.message}</p>
                                        ) : null}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="capabilitiesMaterials">Materials</Label>
                                        <Textarea id="capabilitiesMaterials" rows={3} placeholder="6061-T6, 17-4PH" {...register('capabilitiesMaterials')} />
                                        {errors.capabilitiesMaterials ? (
                                            <p className="text-xs text-destructive">{errors.capabilitiesMaterials.message}</p>
                                        ) : null}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="capabilitiesFinishes">Finishes</Label>
                                        <Textarea id="capabilitiesFinishes" rows={3} placeholder="Anodizing, powder coat" {...register('capabilitiesFinishes')} />
                                        {errors.capabilitiesFinishes ? (
                                            <p className="text-xs text-destructive">{errors.capabilitiesFinishes.message}</p>
                                        ) : null}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="capabilitiesTolerances">Tolerances</Label>
                                        <Textarea id="capabilitiesTolerances" rows={3} placeholder="±0.05 mm" {...register('capabilitiesTolerances')} />
                                        {errors.capabilitiesTolerances ? (
                                            <p className="text-xs text-destructive">{errors.capabilitiesTolerances.message}</p>
                                        ) : null}
                                    </div>
                                    <div className="space-y-2 md:col-span-2">
                                        <Label htmlFor="capabilitiesIndustries">Industries served</Label>
                                        <Textarea id="capabilitiesIndustries" rows={3} placeholder="Aerospace, MedTech" {...register('capabilitiesIndustries')} />
                                        {errors.capabilitiesIndustries ? (
                                            <p className="text-xs text-destructive">{errors.capabilitiesIndustries.message}</p>
                                        ) : null}
                                    </div>
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="certifications">Certifications</Label>
                                    <Textarea id="certifications" rows={3} placeholder="ISO 9001, AS9100" {...register('certifications')} />
                                    {errors.certifications ? (
                                        <p className="text-xs text-destructive">{errors.certifications.message}</p>
                                    ) : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="facilities">Facilities / equipment</Label>
                                    <Textarea id="facilities" rows={3} placeholder="5-axis mills, CMM" {...register('facilities')} />
                                    {errors.facilities ? <p className="text-xs text-destructive">{errors.facilities.message}</p> : null}
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-3">
                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="contactName">Primary contact</Label>
                                    <Input id="contactName" placeholder="Jordan Supplier" {...register('contactName')} />
                                    {errors.contactName ? <p className="text-xs text-destructive">{errors.contactName.message}</p> : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="contactPhone">Phone</Label>
                                    <Input id="contactPhone" placeholder="+1 555-1234" {...register('contactPhone')} />
                                    {errors.contactPhone ? <p className="text-xs text-destructive">{errors.contactPhone.message}</p> : null}
                                </div>
                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="contactEmail">Contact email</Label>
                                    <Input id="contactEmail" type="email" placeholder="supplier@example.com" {...register('contactEmail')} />
                                    {errors.contactEmail ? <p className="text-xs text-destructive">{errors.contactEmail.message}</p> : null}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="notes">Notes</Label>
                                    <Textarea id="notes" rows={3} placeholder="Anything else we should know?" {...register('notes')} />
                                    {errors.notes ? <p className="text-xs text-destructive">{errors.notes.message}</p> : null}
                                </div>
                            </div>

                            {formError ? (
                                <Alert variant="destructive">
                                    <AlertDescription>{formError}</AlertDescription>
                                </Alert>
                            ) : null}

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => (!isSubmitting && !applyMutation.isPending ? setDialogOpen(false) : null)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={isSubmitting || applyMutation.isPending}>
                                    {isSubmitting || applyMutation.isPending ? (
                                        <span className="inline-flex items-center gap-2">
                                            <Loader2 className="h-4 w-4 animate-spin" /> Submitting…
                                        </span>
                                    ) : (
                                        'Submit application'
                                    )}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </CardContent>
        </Card>
    );
}
