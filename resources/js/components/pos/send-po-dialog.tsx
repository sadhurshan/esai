import { zodResolver } from '@hookform/resolvers/zod';
import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';

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
import { formatDate } from '@/lib/format';
import type { PurchaseOrderDelivery } from '@/types/sourcing';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/i;

const sendPoFormSchema = z.object({
    message: z.string().max(2000, 'Message is too long.').optional(),
    fallbackEmail: z.string().optional(),
});

type SendPoFormValues = z.infer<typeof sendPoFormSchema>;

export interface SendPoDialogPayload {
    message?: string;
    overrideEmail?: string;
}

export interface SendPoDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSubmit: (payload: SendPoDialogPayload) => void;
    isSubmitting?: boolean;
    supplierName?: string | null;
    supplierEmail?: string | null;
    latestDelivery?: PurchaseOrderDelivery | null;
}

export function SendPoDialog({
    open,
    onOpenChange,
    onSubmit,
    isSubmitting = false,
    supplierName,
    supplierEmail,
    latestDelivery,
}: SendPoDialogProps) {
    const form = useForm<SendPoFormValues>({
        resolver: zodResolver(sendPoFormSchema),
        defaultValues: {
            message: '',
            fallbackEmail: '',
        },
    });

    useEffect(() => {
        if (open) {
            form.reset({
                message: '',
                fallbackEmail: '',
            });
        }
    }, [open, form]);

    const handleSubmit = form.handleSubmit((values) => {
        if (!supplierEmail) {
            const fallback = values.fallbackEmail?.trim() ?? '';

            if (!fallback) {
                form.setError('fallbackEmail', {
                    type: 'manual',
                    message: 'Provide an email address for this supplier.',
                });
                return;
            }

            if (!EMAIL_PATTERN.test(fallback)) {
                form.setError('fallbackEmail', {
                    type: 'manual',
                    message: 'Enter a valid email address.',
                });
                return;
            }
        }

        const payload: SendPoDialogPayload = {
            message: values.message?.trim() ? values.message.trim() : undefined,
        };

        if (!supplierEmail) {
            payload.overrideEmail = values.fallbackEmail?.trim();
        }

        onSubmit(payload);
    });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Send purchase order</DialogTitle>
                    <DialogDescription>
                        {supplierName
                            ? `Dispatch PO to ${supplierName}.`
                            : 'Dispatch this PO to the supplier.'}
                    </DialogDescription>
                </DialogHeader>

                <form className="space-y-4" onSubmit={handleSubmit}>
                    <div
                        className="rounded-md border border-dashed border-muted-foreground/60 bg-muted/30 p-3 text-sm text-muted-foreground"
                        role="status"
                    >
                        <div className="font-semibold text-foreground">
                            Automatic delivery
                        </div>
                        {supplierEmail ? (
                            <p>
                                We will email {supplierName ?? 'the supplier'}{' '}
                                at{' '}
                                <span className="font-medium">
                                    {supplierEmail}
                                </span>{' '}
                                and simultaneously post this PO to their webhook
                                endpoint.
                            </p>
                        ) : (
                            <p className="text-foreground">
                                This supplier is missing a saved email. Enter a
                                one-time address below to send this PO now, or
                                update the supplier profile later to avoid this
                                step.
                            </p>
                        )}
                    </div>

                    {!supplierEmail ? (
                        <div className="grid gap-2">
                            <Label htmlFor="po-send-fallback">
                                Supplier email
                            </Label>
                            <Input
                                id="po-send-fallback"
                                type="email"
                                placeholder="supplier@example.com"
                                {...form.register('fallbackEmail')}
                            />
                            {form.formState.errors.fallbackEmail ? (
                                <p className="text-sm text-destructive">
                                    {
                                        form.formState.errors.fallbackEmail
                                            .message
                                    }
                                </p>
                            ) : (
                                <p className="text-xs text-muted-foreground">
                                    This address is used for this send only;
                                    update the supplier profile to save it
                                    permanently.
                                </p>
                            )}
                        </div>
                    ) : null}

                    <div className="grid gap-2">
                        <Label htmlFor="po-send-message">Message</Label>
                        <Textarea
                            id="po-send-message"
                            rows={4}
                            placeholder="Add context for the supplier (optional)"
                            {...form.register('message')}
                        />
                        {form.formState.errors.message ? (
                            <p className="text-sm text-destructive">
                                {form.formState.errors.message.message}
                            </p>
                        ) : (
                            <p className="text-xs text-muted-foreground">
                                Optional note that will accompany the email
                                notification.
                            </p>
                        )}
                    </div>

                    {latestDelivery ? (
                        <div className="rounded-md border border-border/70 bg-muted/20 p-3 text-xs text-muted-foreground">
                            <div className="font-semibold text-foreground">
                                Last delivery
                            </div>
                            <div>Status: {latestDelivery.status}</div>
                            {latestDelivery.sentAt ? (
                                <div>
                                    Sent: {formatDate(latestDelivery.sentAt)}
                                </div>
                            ) : null}
                            {latestDelivery.errorReason ? (
                                <div>Error: {latestDelivery.errorReason}</div>
                            ) : null}
                        </div>
                    ) : null}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => onOpenChange(false)}
                            disabled={isSubmitting}
                        >
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
