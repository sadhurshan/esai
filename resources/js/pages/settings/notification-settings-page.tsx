import { useCallback, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Bell, Inbox, Loader2 } from 'lucide-react';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { publishToast } from '@/components/ui/use-toast';
import { ApiError } from '@/lib/api';
import {
    useNotificationPreferences,
    useUpdateNotificationPreference,
    type UpdateNotificationPreferencePayload,
} from '@/hooks/api/settings';
import type {
    NotificationChannel,
    NotificationDigestFrequency,
    NotificationEventType,
    NotificationPreferenceSetting,
} from '@/types/notifications';

interface NotificationEventDefinition {
    eventType: NotificationEventType;
    label: string;
    description: string;
}

interface NotificationEventGroup {
    title: string;
    description: string;
    events: NotificationEventDefinition[];
}

const CHANNEL_OPTIONS: { value: NotificationChannel; label: string; description: string }[] = [
    { value: 'both', label: 'Email + push', description: 'Send email and in-app/push notifications.' },
    { value: 'email', label: 'Email only', description: 'Send email notifications only.' },
    { value: 'push', label: 'Push only', description: 'Send only in-app/push notifications.' },
];

const DIGEST_OPTIONS: { value: NotificationDigestFrequency; label: string; description: string }[] = [
    { value: 'none', label: 'Realtime', description: 'Deliver every event immediately.' },
    { value: 'daily', label: 'Daily digest', description: 'Bundle events into a daily summary.' },
    { value: 'weekly', label: 'Weekly digest', description: 'Bundle events into a weekly summary.' },
];

const DEFAULT_PREFERENCE: NotificationPreferenceSetting = {
    channel: 'both',
    digest: 'none',
};

const EVENT_GROUPS: NotificationEventGroup[] = [
    {
        title: 'RFQs',
        description: 'Keep sourcing teams alerted as RFQs evolve.',
        events: [
            { eventType: 'rfq_created', label: 'RFQ published', description: 'When a new RFQ is published or reopened.' },
            {
                eventType: 'rfq.clarification.question',
                label: 'Clarification question',
                description: 'Suppliers submit a question on an RFQ.',
            },
            {
                eventType: 'rfq.clarification.answer',
                label: 'Clarification answer',
                description: 'Your team responds to a clarification.',
            },
            {
                eventType: 'rfq.clarification.amendment',
                label: 'Clarification amendment',
                description: 'RFQ clarifications are amended or retracted.',
            },
            {
                eventType: 'rfq.deadline.extended',
                label: 'RFQ deadline extended',
                description: 'Buyers extend the submission deadline for an RFQ.',
            },
            {
                eventType: 'rfq_line_awarded',
                label: 'RFQ line awarded',
                description: 'A supplier is awarded work on a line item.',
            },
            {
                eventType: 'rfq_line_lost',
                label: 'RFQ line lost',
                description: 'Your quote loses an RFQ line item.',
            },
        ],
    },
    {
        title: 'Quotes',
        description: 'Respond quickly when suppliers take action.',
        events: [
            { eventType: 'quote_submitted', label: 'Quote submitted', description: 'A supplier submits a quote response.' },
            {
                eventType: 'quote.revision.submitted',
                label: 'Quote revision submitted',
                description: 'Suppliers send revised pricing for review.',
            },
            {
                eventType: 'quote.withdrawn',
                label: 'Quote withdrawn',
                description: 'Suppliers withdraw their responses before award.',
            },
        ],
    },
    {
        title: 'Orders & receiving',
        description: 'Operational alerts for downstream execution.',
        events: [
            { eventType: 'po_issued', label: 'Purchase order issued', description: 'A PO is released to a supplier.' },
            { eventType: 'grn_posted', label: 'Goods received', description: 'Receiving logs a GRN for your PO.' },
        ],
    },
    {
        title: 'Invoicing',
        description: 'Billing lifecycle alerts.',
        events: [
            { eventType: 'invoice_created', label: 'Invoice created', description: 'New invoice received from supplier.' },
            {
                eventType: 'invoice_status_changed',
                label: 'Invoice status updated',
                description: 'An AP reviewer moves an invoice forward or backward.',
            },
        ],
    },
    {
        title: 'Platform alerts',
        description: 'Policy, approvals, and plan guardrails.',
        events: [
            {
                eventType: 'approvals.pending',
                label: 'Approval pending',
                description: 'Workflow items require your approval.',
            },
            {
                eventType: 'plan_overlimit',
                label: 'Plan limit approaching',
                description: 'Usage thresholds exceed contracted limits.',
            },
            {
                eventType: 'certificate_expiry',
                label: 'Certificate expiring',
                description: 'Supplier certifications are about to expire.',
            },
            {
                eventType: 'analytics_query',
                label: 'Analytics export ready',
                description: 'Long-running analytics reports finish processing.',
            },
        ],
    },
    {
        title: 'Returns & RMAs',
        description: 'Reverse logistics, RMA, and service alerts.',
        events: [
            { eventType: 'rma.raised', label: 'RMA raised', description: 'A return material authorization is created.' },
            {
                eventType: 'rma.reviewed',
                label: 'RMA reviewed',
                description: 'Quality reviews the RMA and adds notes.',
            },
            { eventType: 'rma.closed', label: 'RMA closed', description: 'The RMA cycle is completed.' },
        ],
    },
];

function normalizePreference(value?: NotificationPreferenceSetting): NotificationPreferenceSetting {
    if (!value) {
        return { ...DEFAULT_PREFERENCE };
    }

    return {
        channel: value.channel ?? DEFAULT_PREFERENCE.channel,
        digest: value.digest ?? DEFAULT_PREFERENCE.digest,
    } as NotificationPreferenceSetting;
}

interface PreferenceRowProps {
    definition: NotificationEventDefinition;
    value: NotificationPreferenceSetting;
    isSaving: boolean;
    onSave: (payload: UpdateNotificationPreferencePayload) => Promise<void>;
}

function PreferenceRow({ definition, value, isSaving, onSave }: PreferenceRowProps) {
    const [channel, setChannel] = useState<NotificationChannel>(() => value.channel);
    const [digest, setDigest] = useState<NotificationDigestFrequency>(() => value.digest);

    const isDirty = channel !== value.channel || digest !== value.digest;

    const handleSave = async () => {
        await onSave({ event_type: definition.eventType, channel, digest });
    };

    return (
        <div className="grid gap-4 rounded-lg border p-4 md:grid-cols-[2fr_1fr_1fr_auto] md:items-center">
            <div>
                <p className="font-medium">{definition.label}</p>
                <p className="text-sm text-muted-foreground">{definition.description}</p>
            </div>
            <div>
                <p className="text-xs font-medium uppercase text-muted-foreground">Channel</p>
                <Select value={channel} onValueChange={(next) => setChannel(next as NotificationChannel)} disabled={isSaving}>
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {CHANNEL_OPTIONS.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                <div className="flex flex-col">
                                    <span>{option.label}</span>
                                    <span className="text-xs text-muted-foreground">{option.description}</span>
                                </div>
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div>
                <p className="text-xs font-medium uppercase text-muted-foreground">Digest</p>
                <Select value={digest} onValueChange={(next) => setDigest(next as NotificationDigestFrequency)} disabled={isSaving}>
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {DIGEST_OPTIONS.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                <div className="flex flex-col">
                                    <span>{option.label}</span>
                                    <span className="text-xs text-muted-foreground">{option.description}</span>
                                </div>
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="flex justify-end">
                <Button onClick={handleSave} disabled={isSaving || !isDirty} variant="secondary">
                    {isSaving ? (
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    ) : (
                        <Inbox className="mr-2 h-4 w-4" />
                    )}
                    Save
                </Button>
            </div>
        </div>
    );
}

export function NotificationSettingsPage() {
    const preferencesQuery = useNotificationPreferences();
    const updatePreference = useUpdateNotificationPreference();
    const [savingEvent, setSavingEvent] = useState<NotificationEventType | null>(null);

    const preferences = useMemo(() => preferencesQuery.data ?? {}, [preferencesQuery.data]);

    const handleSave = useCallback(
        async (payload: UpdateNotificationPreferencePayload) => {
            try {
                setSavingEvent(payload.event_type);
                await updatePreference.mutateAsync(payload);
                publishToast({
                    variant: 'success',
                    title: 'Preference saved',
                    description: 'Notification preference updated successfully.',
                });
            } catch (error) {
                const message = error instanceof ApiError ? error.message : 'Unable to save your preference right now.';
                publishToast({
                    variant: 'destructive',
                    title: 'Save failed',
                    description: message,
                });
            } finally {
                setSavingEvent(null);
            }
        },
        [updatePreference],
    );

    const isLoading = preferencesQuery.isLoading && !preferencesQuery.data;

    if (isLoading) {
        return <Skeleton className="h-[600px] w-full" />;
    }

    return (
        <div className="space-y-6">
            <Helmet>
                <title>Notification preferences · Elements Supply</title>
            </Helmet>
            <div>
                <p className="text-sm text-muted-foreground">Workspace · Settings</p>
                <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                    <Bell className="h-6 w-6 text-muted-foreground" /> Notification preferences
                </h1>
                <p className="text-sm text-muted-foreground">
                    Choose how and when you are notified for RFQs, quotes, operations, and platform alerts. Preferences apply only to your user profile.
                </p>
            </div>
            {EVENT_GROUPS.map((group) => (
                <Card key={group.title}>
                    <CardHeader>
                        <CardTitle>{group.title}</CardTitle>
                        <CardDescription>{group.description}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {group.events.map((event) => {
                            const current = normalizePreference(preferences[event.eventType]);

                            return (
                                <PreferenceRow
                                    key={`${event.eventType}-${current.channel}-${current.digest}`}
                                    definition={event}
                                    value={current}
                                    isSaving={savingEvent === event.eventType && updatePreference.isPending}
                                    onSave={handleSave}
                                />
                            );
                        })}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
