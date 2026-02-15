import { zodResolver } from '@hookform/resolvers/zod';
import { useMemo, useState } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { z } from 'zod';

import { SupplierDirectoryPicker } from '@/components/rfqs/supplier-directory-picker';
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
import { publishToast } from '@/components/ui/use-toast';
import { useAuth } from '@/contexts/auth-context';
import { useInviteSuppliers } from '@/hooks/api/rfqs';
import type { Supplier } from '@/types/sourcing';

const inviteSchema = z.object({
    supplierIds: z
        .string()
        .min(1, 'Provide at least one supplier email or identifier.'),
    message: z.string().optional(),
});

export type InviteSuppliersFormValues = z.infer<typeof inviteSchema>;

export interface InviteSuppliersDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    rfqId?: string | number;
}

function splitSupplierEntries(raw: string): string[] {
    return raw
        .split(/\r?\n|,/)
        .map((entry) => entry.trim())
        .filter((entry): entry is string => entry.length > 0);
}

export function InviteSuppliersDialog({
    open,
    onOpenChange,
    rfqId,
}: InviteSuppliersDialogProps) {
    const inviteMutation = useInviteSuppliers();
    const { hasFeature, state: authState } = useAuth();
    const featureFlagsLoaded =
        Object.keys(authState.featureFlags ?? {}).length > 0;
    const canInviteSuppliers = featureFlagsLoaded
        ? hasFeature('rfqs.suppliers.invite')
        : true;
    const canBrowseSupplierDirectory = featureFlagsLoaded
        ? hasFeature('suppliers.directory.browse')
        : true;
    const [isDirectoryPickerOpen, setDirectoryPickerOpen] = useState(false);
    const directoryPickerOpen = open && isDirectoryPickerOpen;

    const form = useForm<InviteSuppliersFormValues>({
        resolver: zodResolver(inviteSchema),
        defaultValues: {
            supplierIds: '',
            message: '',
        },
    });

    const isSubmitting = inviteMutation.isPending;

    const supplierIdsRaw = useWatch({
        control: form.control,
        name: 'supplierIds',
    });

    const parsedSuppliers = useMemo(() => {
        return splitSupplierEntries(supplierIdsRaw ?? '');
    }, [supplierIdsRaw]);

    const handleSupplierSelectedFromDirectory = (supplier: Supplier) => {
        if (!canInviteSuppliers) {
            return;
        }

        const existing = splitSupplierEntries(
            form.getValues('supplierIds') ?? '',
        );
        const identifier = String(supplier.id);

        if (existing.includes(identifier)) {
            publishToast({
                variant: 'default',
                title: 'Supplier already added',
                description: `${supplier.name} is already queued for invitations.`,
            });
            return;
        }

        const next = [...existing, identifier];
        form.setValue('supplierIds', next.join('\n'), {
            shouldDirty: true,
            shouldTouch: true,
        });
        publishToast({
            variant: 'success',
            title: 'Supplier added',
            description: `${supplier.name} will be invited when you send this batch.`,
        });
    };

    const handleSubmit = form.handleSubmit(async (values) => {
        if (!rfqId) {
            publishToast({
                variant: 'destructive',
                title: 'Missing RFQ context',
                description: 'Unable to send invitations without an RFQ ID.',
            });
            return;
        }

        if (!canInviteSuppliers) {
            publishToast({
                variant: 'destructive',
                title: 'Invitations unavailable',
                description:
                    'Upgrade your plan to invite suppliers from the directory.',
            });
            return;
        }

        const uniqueSupplierIds = Array.from(
            new Set(splitSupplierEntries(values.supplierIds)),
        );

        try {
            await inviteMutation.mutateAsync({
                rfqId,
                supplierIds: uniqueSupplierIds,
            });
            publishToast({
                variant: 'success',
                title: 'Invitations queued',
                description: `Invited ${uniqueSupplierIds.length} supplier(s) to this RFQ.`,
            });
            form.reset();
            onOpenChange(false);
        } catch (error) {
            const message =
                error instanceof Error
                    ? error.message
                    : 'Unable to send invitations.';
            publishToast({
                variant: 'destructive',
                title: 'Invitation failed',
                description: message,
            });
        }
    });

    const handleDialogOpenChange = (nextOpen: boolean) => {
        if (!nextOpen) {
            setDirectoryPickerOpen(false);
        }
        onOpenChange(nextOpen);
    };

    return (
        <Dialog open={open} onOpenChange={handleDialogOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Invite suppliers</DialogTitle>
                    <DialogDescription>
                        Paste supplier emails or directory identifiers, one per
                        line. Duplicate entries will be removed automatically
                        before dispatch.
                    </DialogDescription>
                </DialogHeader>

                <form className="grid gap-4" onSubmit={handleSubmit}>
                    <div className="grid gap-2">
                        <div className="flex items-center justify-between gap-2">
                            <Label htmlFor="supplierIds">
                                Supplier emails or IDs
                            </Label>
                            {canInviteSuppliers &&
                            canBrowseSupplierDirectory ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setDirectoryPickerOpen(true)}
                                >
                                    Browse directory
                                </Button>
                            ) : null}
                        </div>
                        <Textarea
                            id="supplierIds"
                            rows={5}
                            placeholder={[
                                'supplier@example.com',
                                'SUP-1234',
                            ].join('\n')}
                            disabled={!canInviteSuppliers}
                            {...form.register('supplierIds')}
                        />
                        {form.formState.errors.supplierIds ? (
                            <p className="text-sm text-destructive">
                                {form.formState.errors.supplierIds.message}
                            </p>
                        ) : null}
                        <p
                            className={`text-xs ${
                                canInviteSuppliers
                                    ? 'text-muted-foreground'
                                    : 'text-destructive'
                            }`}
                        >
                            {canInviteSuppliers
                                ? canBrowseSupplierDirectory
                                    ? 'Paste one supplier per line or browse the directory to add approved vendors.'
                                    : 'Paste one supplier per line. Directory browsing requires an upgraded plan.'
                                : 'Upgrade your plan to invite suppliers to RFQs.'}
                        </p>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="message">Optional message</Label>
                        <Input
                            id="message"
                            placeholder="Add a note that will accompany the invitation email"
                            {...form.register('message')}
                        />
                    </div>

                    <div className="text-xs text-muted-foreground">
                        {parsedSuppliers.length} recipient
                        {parsedSuppliers.length === 1 ? '' : 's'} will be
                        invited.
                    </div>

                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={isSubmitting || !canInviteSuppliers}
                        >
                            Send invitations
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
            {canInviteSuppliers && canBrowseSupplierDirectory ? (
                <SupplierDirectoryPicker
                    open={directoryPickerOpen}
                    onOpenChange={setDirectoryPickerOpen}
                    onSelect={handleSupplierSelectedFromDirectory}
                />
            ) : null}
        </Dialog>
    );
}
