import { zodResolver } from '@hookform/resolvers/zod';
import { useMemo } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { z } from 'zod';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import type { CreateWebhookPayload, WebhookRetryPolicyInput, WebhookSubscriptionItem } from '@/types/admin';
import { cn } from '@/lib/utils';

const webhookFormSchema = z.object({
    companyId: z.coerce.number().int().positive('Select a company.'),
    url: z.string().url('Provide a valid HTTPS endpoint.'),
    secret: z
        .preprocess((value) => (typeof value === 'string' && value.trim().length > 0 ? value.trim() : undefined), z.string().min(16).max(128).optional()),
    events: z.array(z.string()).min(1, 'Select at least one event.'),
    active: z.boolean(),
    retryBackoff: z.enum(['exponential', 'linear']).default('exponential'),
    retryMax: numericField(1, 20),
    retryBaseSeconds: numericField(5, 3600),
});

type WebhookFormSchema = z.infer<typeof webhookFormSchema>;

export interface WebhookEventOption {
    value: string;
    label: string;
    description?: string;
}

export interface CompanyOption {
    value: number;
    label: string;
}

export interface WebhookEndpointEditorProps {
    subscription?: WebhookSubscriptionItem | null;
    availableEvents: WebhookEventOption[];
    companyOptions?: CompanyOption[];
    isSubmitting?: boolean;
    onSubmit: (payload: CreateWebhookPayload) => Promise<void> | void;
    onCancel?: () => void;
    mode?: 'create' | 'edit';
}

export function WebhookEndpointEditor({
    subscription,
    availableEvents,
    companyOptions,
    isSubmitting = false,
    onSubmit,
    onCancel,
    mode = 'create',
}: WebhookEndpointEditorProps) {
    const defaultCompanyId = useMemo(() => {
        if (subscription?.companyId) {
            return subscription.companyId;
        }
        if (companyOptions?.length) {
            return companyOptions[0].value;
        }
        return 0;
    }, [companyOptions, subscription?.companyId]);

    const defaultRetryPolicy = extractRetryPolicy(subscription);
    const safeRetryBackoff: 'exponential' | 'linear' = defaultRetryPolicy.backoff === 'linear' ? 'linear' : 'exponential';

    const form = useForm<WebhookFormSchema>({
        resolver: zodResolver(webhookFormSchema),
        defaultValues: {
            companyId: defaultCompanyId,
            url: subscription?.url ?? '',
            secret: '',
            events: subscription?.events ?? [],
            active: subscription?.active ?? true,
            retryBackoff: safeRetryBackoff,
            retryMax: defaultRetryPolicy.max,
            retryBaseSeconds: defaultRetryPolicy.baseSeconds,
        },
    });

    const companyIdValue = useWatch({ control: form.control, name: 'companyId' });
    const selectedEvents = useWatch({ control: form.control, name: 'events' });
    const activeValue = useWatch({ control: form.control, name: 'active' });
    const retryBackoffValue = useWatch({ control: form.control, name: 'retryBackoff' }) ?? safeRetryBackoff;

    const handleSubmit = form.handleSubmit(async (values) => {
        const payload: CreateWebhookPayload = {
            companyId: values.companyId,
            url: values.url.trim(),
            events: values.events,
            active: values.active,
            retryPolicy: {
                backoff: values.retryBackoff,
                max: values.retryMax,
                baseSeconds: values.retryBaseSeconds,
            } satisfies WebhookRetryPolicyInput,
        };

        if (values.secret) {
            payload.secret = values.secret;
        }

        await onSubmit(payload);
    });

    const handleToggleEvent = (eventValue: string, checked: boolean) => {
        const current = new Set(selectedEvents ?? []);
        if (checked) {
            current.add(eventValue);
        } else {
            current.delete(eventValue);
        }
        form.setValue('events', Array.from(current));
    };

    const handleGenerateSecret = () => {
        const secret = generateSecret();
        form.setValue('secret', secret, { shouldValidate: true, shouldDirty: true });
    };

    const handleSelectAllEvents = () => {
        form.setValue(
            'events',
            availableEvents.map((option) => option.value),
        );
    };

    const handleClearEvents = () => {
        form.setValue('events', []);
    };

    return (
        <form className="space-y-6" onSubmit={handleSubmit}>
            <section className="space-y-4 rounded-xl border bg-card p-4">
                <div className="flex flex-col gap-1">
                    <h3 className="text-base font-semibold">Destination</h3>
                    <p className="text-sm text-muted-foreground">Choose which tenant and URL should receive events.</p>
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="webhook-company">Company</Label>
                        {companyOptions?.length ? (
                                <Select
                                    value={String(companyIdValue || '')}
                                onValueChange={(value) => form.setValue('companyId', Number(value), { shouldValidate: true })}
                            >
                                <SelectTrigger id="webhook-company">
                                    <SelectValue placeholder="Select company" />
                                </SelectTrigger>
                                <SelectContent>
                                    {companyOptions.map((option) => (
                                        <SelectItem key={option.value} value={String(option.value)}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        ) : (
                            <Input value={subscription?.companyId ? `Company #${subscription.companyId}` : 'Select company'} disabled />
                        )}
                        {form.formState.errors.companyId ? (
                            <p className="text-sm text-destructive">{form.formState.errors.companyId.message}</p>
                        ) : null}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="webhook-url">Endpoint URL</Label>
                        <Input
                            id="webhook-url"
                            placeholder="https://example.com/webhooks"
                            {...form.register('url')}
                            aria-invalid={Boolean(form.formState.errors.url)}
                        />
                        {form.formState.errors.url ? (
                            <p className="text-sm text-destructive">{form.formState.errors.url.message}</p>
                        ) : (
                            <p className="text-xs text-muted-foreground">Must accept HTTPS POST requests.</p>
                        )}
                    </div>
                </div>
                <div className="flex flex-col gap-2">
                    <Label>Activation</Label>
                    <label className="flex items-center gap-3 text-sm">
                        <Checkbox
                            checked={Boolean(activeValue)}
                            onCheckedChange={(checked) => form.setValue('active', Boolean(checked))}
                        />
                        <span>Webhook is active</span>
                    </label>
                    <p className="text-xs text-muted-foreground">Inactive endpoints will queue events but skip delivery attempts.</p>
                </div>
            </section>

            <section className="space-y-4 rounded-xl border bg-card p-4">
                <div className="flex flex-col gap-1">
                    <h3 className="text-base font-semibold">Signing secret</h3>
                    <p className="text-sm text-muted-foreground">We include this secret in the `X-Webhook-Signature` header.</p>
                </div>
                <div className="grid gap-4 md:grid-cols-[2fr_1fr]">
                    <div className="space-y-2">
                        <Label htmlFor="webhook-secret">Secret (optional)</Label>
                        <Input
                            id="webhook-secret"
                            placeholder="Leave blank to auto-generate"
                            {...form.register('secret')}
                            aria-invalid={Boolean(form.formState.errors.secret)}
                        />
                        {form.formState.errors.secret ? (
                            <p className="text-sm text-destructive">{form.formState.errors.secret.message}</p>
                        ) : subscription?.secretHint ? (
                            <p className="text-xs text-muted-foreground">Current secret ends with {subscription.secretHint}.</p>
                        ) : (
                            <p className="text-xs text-muted-foreground">Minimum 16 characters. Leave empty to keep the current value.</p>
                        )}
                    </div>
                    <div className="flex items-end">
                        <Button type="button" variant="outline" className="w-full" onClick={handleGenerateSecret}>
                            Generate secret
                        </Button>
                    </div>
                </div>
            </section>

            <section className="space-y-4 rounded-xl border bg-card p-4">
                <div className="flex flex-col gap-1">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 className="text-base font-semibold">Subscribed events</h3>
                            <p className="text-sm text-muted-foreground">Select which notifications this endpoint should receive.</p>
                        </div>
                        <div className="flex gap-2 text-xs">
                            <Button type="button" variant="ghost" onClick={handleSelectAllEvents}>
                                Select all
                            </Button>
                            <Button type="button" variant="ghost" onClick={handleClearEvents}>
                                Clear
                            </Button>
                        </div>
                    </div>
                </div>
                <div className="grid gap-3 md:grid-cols-2">
                    {availableEvents.map((event) => {
                        const checked = selectedEvents?.includes(event.value) ?? false;
                        return (
                            <label
                                key={event.value}
                                className={cn(
                                    'flex items-start gap-3 rounded-lg border bg-background p-3 text-sm',
                                    checked ? 'border-primary/60 shadow-sm' : 'border-muted',
                                )}
                            >
                                <Checkbox
                                    checked={checked}
                                    onCheckedChange={(value) => handleToggleEvent(event.value, Boolean(value))}
                                />
                                <span className="space-y-1">
                                    <span className="flex items-center gap-2 font-medium">
                                        {event.label}
                                        <Badge variant="outline" className="text-[10px] uppercase tracking-wide">
                                            {event.value}
                                        </Badge>
                                    </span>
                                    <span className="block text-xs text-muted-foreground">{event.description}</span>
                                </span>
                            </label>
                        );
                    })}
                </div>
                {form.formState.errors.events ? (
                    <p className="text-sm text-destructive">{form.formState.errors.events.message}</p>
                ) : null}
            </section>

            <section className="space-y-4 rounded-xl border bg-card p-4">
                <div className="flex flex-col gap-1">
                    <h3 className="text-base font-semibold">Retry policy</h3>
                    <p className="text-sm text-muted-foreground">Control how often we attempt deliveries before dead-lettering.</p>
                </div>
                <div className="grid gap-4 md:grid-cols-3">
                    <div className="space-y-2">
                        <Label htmlFor="webhook-retry-max">Max attempts</Label>
                        <Input
                            id="webhook-retry-max"
                            type="number"
                            min={1}
                            max={20}
                            placeholder="5"
                            {...form.register('retryMax')}
                        />
                        {form.formState.errors.retryMax ? (
                            <p className="text-sm text-destructive">{form.formState.errors.retryMax.message}</p>
                        ) : (
                            <p className="text-xs text-muted-foreground">Defaults to 5 attempts.</p>
                        )}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="webhook-retry-base">Base delay (sec)</Label>
                        <Input
                            id="webhook-retry-base"
                            type="number"
                            min={5}
                            max={3600}
                            placeholder="30"
                            {...form.register('retryBaseSeconds')}
                        />
                        {form.formState.errors.retryBaseSeconds ? (
                            <p className="text-sm text-destructive">{form.formState.errors.retryBaseSeconds.message}</p>
                        ) : (
                            <p className="text-xs text-muted-foreground">First retry waits this long before applying backoff.</p>
                        )}
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="webhook-retry-backoff">Strategy</Label>
                        <Select
                            value={retryBackoffValue}
                            onValueChange={(value) => form.setValue('retryBackoff', value as 'exponential' | 'linear')}
                        >
                            <SelectTrigger id="webhook-retry-backoff">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="exponential">Exponential</SelectItem>
                                <SelectItem value="linear">Linear</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </section>

            <Separator />

            <div className="flex flex-wrap justify-end gap-3">
                {onCancel ? (
                    <Button type="button" variant="outline" onClick={onCancel} disabled={isSubmitting}>
                        Cancel
                    </Button>
                ) : null}
                <Button type="submit" disabled={isSubmitting}>
                    {isSubmitting ? 'Saving...' : mode === 'edit' ? 'Save changes' : 'Create webhook'}
                </Button>
            </div>
        </form>
    );
}

function extractRetryPolicy(subscription?: WebhookSubscriptionItem | null): WebhookRetryPolicyInput {
    const fallback: WebhookRetryPolicyInput = {
        backoff: 'exponential',
        max: undefined,
        baseSeconds: undefined,
    };

    if (!subscription?.retryPolicy || typeof subscription.retryPolicy !== 'object') {
        return fallback;
    }

    const policy = subscription.retryPolicy as Record<string, unknown>;
    return {
        backoff: policy.backoff === 'linear' ? 'linear' : 'exponential',
        max: typeof policy.max === 'number' ? policy.max : undefined,
        baseSeconds: typeof policy.base_sec === 'number' ? policy.base_sec : undefined,
    };
}

function numericField(min: number, max: number) {
    return z
        .preprocess((value) => {
            if (value === '' || value === null || value === undefined) {
                return undefined;
            }
            const nextValue = Number(value);
            return Number.isNaN(nextValue) ? undefined : nextValue;
        }, z.number().int().min(min).max(max).optional());
}

function generateSecret(length = 48) {
    const cryptoRef = typeof globalThis !== 'undefined' ? globalThis.crypto : undefined;
    if (cryptoRef?.getRandomValues) {
        const bytes = new Uint8Array(length);
        cryptoRef.getRandomValues(bytes);
        return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('').slice(0, length);
    }

    return Array.from({ length }, () => Math.floor(Math.random() * 16).toString(16)).join('');
}
