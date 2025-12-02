import { useEffect, useMemo } from 'react';
import { Helmet } from 'react-helmet-async';
import { useForm, useWatch } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { publishToast } from '@/components/ui/use-toast';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useProfile, useUpdateProfile, type UpdateProfilePayload } from '@/hooks/api/settings';
import { useAuth } from '@/contexts/auth-context';
import { ApiError } from '@/lib/api';
import { useInitials } from '@/hooks/use-initials';
import type { User } from '@/types';
import { useSwitchCompany, useUserCompanies } from '@/hooks/api/use-user-companies';

const LOCALE_OPTIONS = [
    { value: 'en', label: 'English (EN)' },
    { value: 'fr', label: 'Français (FR)' },
    { value: 'de', label: 'Deutsch (DE)' },
    { value: 'es', label: 'Español (ES)' },
    { value: 'ja', label: '日本語 (JA)' },
];

const SYSTEM_DEFAULT_VALUE = '__system_default__';

const TIMEZONE_OPTIONS = [
    'UTC',
    'America/New_York',
    'America/Los_Angeles',
    'America/Chicago',
    'Europe/London',
    'Europe/Berlin',
    'Europe/Paris',
    'Asia/Dubai',
    'Asia/Kolkata',
    'Asia/Singapore',
    'Asia/Tokyo',
    'Australia/Sydney',
];

const profileSchema = z.object({
    name: z.string().min(1, 'Name is required.').max(255),
    email: z.string().email('Enter a valid email.').max(255),
    jobTitle: z.string().max(120).optional().nullable(),
    phone: z.string().max(32).optional().nullable(),
    locale: z.string().max(10).optional().nullable(),
    timezone: z.string().max(64).optional().nullable(),
    avatarPath: z.string().max(255).optional().nullable(),
});

type ProfileFormValues = z.infer<typeof profileSchema>;

const emptyValues: ProfileFormValues = {
    name: '',
    email: '',
    jobTitle: '',
    phone: '',
    locale: '',
    timezone: '',
    avatarPath: '',
};

function toFormValues(profile?: User | null): ProfileFormValues {
    if (!profile) {
        return emptyValues;
    }

    return {
        name: profile.name ?? '',
        email: profile.email ?? '',
        jobTitle: profile.job_title ?? '',
        phone: profile.phone ?? '',
        locale: profile.locale ?? '',
        timezone: profile.timezone ?? '',
        avatarPath: profile.avatar_path ?? '',
    } satisfies ProfileFormValues;
}

function toPayload(values: ProfileFormValues): UpdateProfilePayload {
    return {
        name: values.name.trim(),
        email: values.email.trim(),
        job_title: values.jobTitle?.trim() || null,
        phone: values.phone?.trim() || null,
        locale: values.locale?.trim() || null,
        timezone: values.timezone?.trim() || null,
        avatar_path: values.avatarPath?.trim() || null,
    } satisfies UpdateProfilePayload;
}

export function ProfileSettingsPage() {
    const profileQuery = useProfile();
    const updateProfile = useUpdateProfile();
    const { refresh } = useAuth();
    const initials = useInitials();
    const companiesQuery = useUserCompanies();
    const switchCompany = useSwitchCompany();

    const form = useForm<ProfileFormValues>({
        resolver: zodResolver(profileSchema),
        mode: 'onBlur',
        defaultValues: toFormValues(profileQuery.data),
    });

    useEffect(() => {
        if (profileQuery.data) {
            form.reset(toFormValues(profileQuery.data));
        }
    }, [profileQuery.data, form]);

    const avatarPath = useWatch({ control: form.control, name: 'avatarPath' });
    const watchedName = useWatch({ control: form.control, name: 'name' });

    const avatarPreview = avatarPath || profileQuery.data?.avatar_url || '';
    const nameFallback = watchedName || profileQuery.data?.name || profileQuery.data?.email || '';

    const handleSubmit = form.handleSubmit(async (values) => {
        try {
            const payload = toPayload(values);
            await updateProfile.mutateAsync(payload);
            await refresh();
            publishToast({
                variant: 'success',
                title: 'Profile updated',
                description: 'Personal information saved successfully.',
            });
        } catch (error) {
            const message = error instanceof ApiError ? error.message : 'Unable to update your profile.';
            publishToast({
                variant: 'destructive',
                title: 'Update failed',
                description: message,
            });
        }
    });

    const isLoading = profileQuery.isLoading && !profileQuery.data;
    const companies = companiesQuery.data ?? [];
    const companiesLoading = companiesQuery.isLoading && companies.length === 0;
    const canManageCompanies = companies.length > 1;

    const timezoneOptions = useMemo(() => TIMEZONE_OPTIONS.map((value) => ({ value, label: value })), []);

    if (isLoading) {
        return <Skeleton className="h-[480px] w-full" />;
    }

    return (
        <div className="space-y-6">
            <Helmet>
                <title>Personal profile · Elements Supply</title>
            </Helmet>
            <div>
                <p className="text-sm text-muted-foreground">Workspace · Settings</p>
                <h1 className="text-2xl font-semibold tracking-tight">Personal profile</h1>
                <p className="text-sm text-muted-foreground">
                    Update your name, contact info, locale preferences, and avatar so teammates know who they are collaborating with.
                </p>
            </div>

            <Form {...form}>
                <form onSubmit={handleSubmit} className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Contact information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 md:grid-cols-2">
                                <FormField
                                    control={form.control}
                                    name="name"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Full name</FormLabel>
                                            <FormControl>
                                                <Input placeholder="Alex Procurement" {...field} />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />
                                <FormField
                                    control={form.control}
                                    name="email"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Email</FormLabel>
                                            <FormControl>
                                                <Input type="email" placeholder="alex@example.com" {...field} />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />
                            </div>
                            <div className="grid gap-4 md:grid-cols-2">
                                <FormField
                                    control={form.control}
                                    name="jobTitle"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Job title</FormLabel>
                                            <FormControl>
                                                <Input placeholder="Sr. Procurement Manager" {...field} value={field.value ?? ''} />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />
                                <FormField
                                    control={form.control}
                                    name="phone"
                                    render={({ field }) => (
                                        <FormItem>
                                            <FormLabel>Phone number</FormLabel>
                                            <FormControl>
                                                <Input placeholder="+1 555 0100" {...field} value={field.value ?? ''} />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    )}
                                />
                            </div>
                        </CardContent>
                        <CardHeader className="border-t">
                            <CardTitle>Localization</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <FormField
                                control={form.control}
                                name="locale"
                                render={({ field }) => (
                                    <FormItem>
                                        <FormLabel>Locale</FormLabel>
                                        <Select
                                            value={field.value && field.value.length > 0 ? field.value : SYSTEM_DEFAULT_VALUE}
                                            onValueChange={(next) =>
                                                field.onChange(next === SYSTEM_DEFAULT_VALUE ? '' : next)
                                            }
                                        >
                                            <FormControl>
                                                <SelectTrigger>
                                                    <SelectValue placeholder="System default" />
                                                </SelectTrigger>
                                            </FormControl>
                                            <SelectContent>
                                                <SelectItem value={SYSTEM_DEFAULT_VALUE}>System default</SelectItem>
                                                {LOCALE_OPTIONS.map((option) => (
                                                    <SelectItem key={option.value} value={option.value}>
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <FormMessage />
                                    </FormItem>
                                )}
                            />
                            <FormField
                                control={form.control}
                                name="timezone"
                                render={({ field }) => (
                                    <FormItem>
                                        <FormLabel>Timezone</FormLabel>
                                        <Select
                                            value={field.value && field.value.length > 0 ? field.value : SYSTEM_DEFAULT_VALUE}
                                            onValueChange={(next) =>
                                                field.onChange(next === SYSTEM_DEFAULT_VALUE ? '' : next)
                                            }
                                        >
                                            <FormControl>
                                                <SelectTrigger>
                                                    <SelectValue placeholder="System default" />
                                                </SelectTrigger>
                                            </FormControl>
                                            <SelectContent className="max-h-72">
                                                <SelectItem value={SYSTEM_DEFAULT_VALUE}>System default</SelectItem>
                                                {timezoneOptions.map((option) => (
                                                    <SelectItem key={option.value} value={option.value}>
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <FormMessage />
                                    </FormItem>
                                )}
                            />
                        </CardContent>
                        <CardHeader className="border-t">
                            <CardTitle>Avatar</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-[2fr_1fr]">
                            <FormField
                                control={form.control}
                                name="avatarPath"
                                render={({ field }) => (
                                    <FormItem>
                                        <FormLabel>Avatar path or URL</FormLabel>
                                        <FormControl>
                                            <Input placeholder="uploads/avatars/alex.png" {...field} value={field.value ?? ''} />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                )}
                            />
                            <div className="flex items-center justify-center">
                                <Avatar className="h-20 w-20">
                                    {avatarPreview ? (
                                        <AvatarImage src={avatarPreview} alt={nameFallback} />
                                    ) : (
                                        <AvatarFallback>{initials(nameFallback)}</AvatarFallback>
                                    )}
                                </Avatar>
                            </div>
                        </CardContent>
                        <CardFooter className="justify-end border-t">
                            <Button type="submit" disabled={updateProfile.isPending}>
                                {updateProfile.isPending ? 'Saving…' : 'Save profile'}
                            </Button>
                        </CardFooter>
                    </Card>
                </form>
            </Form>

            <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                {companiesLoading ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Default organization</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Skeleton className="h-32 w-full" />
                        </CardContent>
                    </Card>
                ) : canManageCompanies ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Default organization</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <p className="text-sm text-muted-foreground">
                                Choose which organization loads by default whenever you sign in. You can still switch at any time from the top bar.
                            </p>
                            <div className="space-y-3">
                                {companies.map((company) => (
                                    <div
                                        key={company.id}
                                        className="flex flex-col gap-2 rounded-lg border p-3 md:flex-row md:items-center md:justify-between"
                                    >
                                        <div>
                                            <p className="font-medium">{company.name}</p>
                                            {company.role ? (
                                                <p className="text-sm capitalize text-muted-foreground">
                                                    {company.role.replace(/_/g, ' ')}
                                                </p>
                                            ) : null}
                                            {company.isActive ? (
                                                <p className="text-xs text-primary">Currently active</p>
                                            ) : null}
                                        </div>
                                        <Button
                                            variant={company.isDefault ? 'secondary' : 'outline'}
                                            size="sm"
                                            disabled={company.isDefault || switchCompany.isPending}
                                            onClick={() => {
                                                switchCompany
                                                    .mutateAsync(company.id)
                                                    .then(() => {
                                                        publishToast({
                                                            variant: 'success',
                                                            title: 'Default organization updated',
                                                            description: `${company.name} will load first next time you sign in.`,
                                                        });
                                                    })
                                                    .catch((error) => {
                                                        const message =
                                                            error instanceof ApiError
                                                                ? error.message
                                                                : 'Unable to update your default organization right now.';
                                                        publishToast({
                                                            variant: 'destructive',
                                                            title: 'Update failed',
                                                            description: message,
                                                        });
                                                    });
                                            }}
                                        >
                                            {company.isDefault ? 'Default' : 'Set as default'}
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle>Organization</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                You currently belong to a single organization. Once another company invites you, you can set a default tenant here.
                            </p>
                        </CardContent>
                    </Card>
                )}

                <div className="hidden lg:block" />
            </div>
        </div>
    );
}
