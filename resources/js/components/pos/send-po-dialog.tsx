import { useEffect } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { z } from 'zod';
import { zodResolver } from '@hookform/resolvers/zod';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { PurchaseOrderDelivery } from '@/types/sourcing';
import { formatDate } from '@/lib/format';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/i;

const sendPoFormSchema = z
    .object({
        channel: z.enum(['email', 'webhook']),
        toRaw: z.string().optional(),
        ccRaw: z.string().optional(),
        message: z.string().max(2000, 'Message is too long.').optional(),
    })
    .superRefine((values, ctx) => {
        if (values.channel === 'email') {
            const recipients = parseRecipients(values.toRaw);
            if (recipients.length === 0) {
                ctx.addIssue({
                    path: ['toRaw'],
                    code: z.ZodIssueCode.custom,
                    message: 'Provide at least one recipient.',
                });
            }

            const invalid = recipients.filter((recipient) => !EMAIL_PATTERN.test(recipient));
            if (invalid.length > 0) {
                ctx.addIssue({
                    path: ['toRaw'],
                    code: z.ZodIssueCode.custom,
                    message: `Invalid email(s): ${invalid.join(', ')}`,
                });
            }

            const ccRecipients = parseRecipients(values.ccRaw);
            const invalidCc = ccRecipients.filter((recipient) => !EMAIL_PATTERN.test(recipient));
            if (invalidCc.length > 0) {
                ctx.addIssue({
                    path: ['ccRaw'],
                    code: z.ZodIssueCode.custom,
                    message: `Invalid email(s): ${invalidCc.join(', ')}`,
                });
            }
        }
    });

type SendPoFormValues = z.infer<typeof sendPoFormSchema>;

export interface SendPoDialogPayload {
    channel: 'email' | 'webhook';
    to?: string[];
    cc?: string[];
    message?: string;
}

export interface SendPoDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSubmit: (payload: SendPoDialogPayload) => void;
    isSubmitting?: boolean;
    defaultChannel?: 'email' | 'webhook';
    supplierName?: string | null;
    latestDelivery?: PurchaseOrderDelivery | null;
}

function parseRecipients(raw?: string | null): string[] {
    if (!raw) {
        return [];
    }

    return raw
        .split(/[\n,;]+/)
        .map((entry) => entry.trim())
        .filter((entry) => entry.length > 0);
}

export function SendPoDialog({
    open,
    onOpenChange,
    onSubmit,
    isSubmitting = false,
    defaultChannel = 'email',
    supplierName,
    latestDelivery,
}: SendPoDialogProps) {
    const form = useForm<SendPoFormValues>({
        resolver: zodResolver(sendPoFormSchema),
        defaultValues: {
            channel: defaultChannel,
            toRaw: '',
            ccRaw: '',
            message: '',
        },
    });

    useEffect(() => {
        if (open) {
            form.reset({
                channel: defaultChannel,
                toRaw: '',
                ccRaw: '',
                message: '',
            });
        }
    }, [open, defaultChannel, form]);

    const channel = useWatch({ control: form.control, name: 'channel' });

    const handleSubmit = form.handleSubmit((values) => {
        const payload: SendPoDialogPayload = {
            channel: values.channel,
            to: values.channel === 'email' ? parseRecipients(values.toRaw) : undefined,
            cc: values.channel === 'email' ? parseRecipients(values.ccRaw) : undefined,
            message: values.message?.trim() ? values.message.trim() : undefined,
        };

        onSubmit(payload);
    });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Send purchase order</DialogTitle>
                    <DialogDescription>
                        {supplierName ? `Dispatch PO to ${supplierName}.` : 'Dispatch this PO to the supplier.'}
                    </DialogDescription>
                </DialogHeader>

                <form className="space-y-4" onSubmit={handleSubmit}>
                    <div className="grid gap-2">
                        <Label htmlFor="channel">Delivery channel</Label>
                        <Select value={channel} onValueChange={(next) => form.setValue('channel', next as 'email' | 'webhook')}>
                            <SelectTrigger id="channel">
                                <SelectValue placeholder="Select channel" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="email">Email</SelectItem>
                                <SelectItem value="webhook">Webhook</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {channel === 'email' ? (
                        <div className="grid gap-6 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="po-send-to">To</Label>
                                <Textarea
                                    id="po-send-to"
                                    placeholder="supplier@example.com, ops@example.com"
                                    rows={3}
                                    {...form.register('toRaw')}
                                />
                                {form.formState.errors.toRaw ? (
                                    <p className="text-sm text-destructive">{form.formState.errors.toRaw.message}</p>
                                ) : null}
                                <p className="text-xs text-muted-foreground">Separate addresses with commas or new lines.</p>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="po-send-cc">CC</Label>
                                <Textarea id="po-send-cc" rows={3} {...form.register('ccRaw')} />
                                {form.formState.errors.ccRaw ? (
                                    <p className="text-sm text-destructive">{form.formState.errors.ccRaw.message}</p>
                                ) : null}
                                <p className="text-xs text-muted-foreground">Optional recipients who should be copied.</p>
                            </div>
                        </div>
                    ) : (
                        <div className="rounded-md border border-dashed border-muted-foreground/60 bg-muted/30 p-3 text-sm text-muted-foreground">
                            The webhook channel will notify the configured endpoint in your supplier integration. No email
                            addresses are required.
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="po-send-message">Message</Label>
                        <Textarea
                            id="po-send-message"
                            rows={4}
                            placeholder="Add context for the supplier (optional)"
                            {...form.register('message')}
                        />
                        {form.formState.errors.message ? (
                            <p className="text-sm text-destructive">{form.formState.errors.message.message}</p>
                        ) : null}
                    </div>

                    {latestDelivery ? (
                        <div className="rounded-md border border-border/70 bg-muted/20 p-3 text-xs text-muted-foreground">
                            <div className="font-semibold text-foreground">Last delivery</div>
                            <div>Status: {latestDelivery.status}</div>
                            {latestDelivery.sentAt ? <div>Sent: {formatDate(latestDelivery.sentAt)}</div> : null}
                            {latestDelivery.errorReason ? <div>Error: {latestDelivery.errorReason}</div> : null}
                        </div>
                    ) : null}

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)} disabled={isSubmitting}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={isSubmitting}>
                            {isSubmitting ? 'Sending…' : 'Send purchase order'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}