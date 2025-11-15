import { useState } from 'react';

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

interface WithdrawConfirmDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: (reason: string) => Promise<void> | void;
    isProcessing?: boolean;
}

export function WithdrawConfirmDialog({ open, onOpenChange, onConfirm, isProcessing = false }: WithdrawConfirmDialogProps) {
    const [reason, setReason] = useState('');

    const handleOpenChange = (nextOpen: boolean) => {
        if (!nextOpen) {
            setReason('');
        }
        onOpenChange(nextOpen);
    };

    const handleConfirm = async () => {
        const trimmed = reason.trim();
        if (trimmed.length < 3) {
            return;
        }
        await onConfirm(trimmed);
        setReason('');
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="space-y-4">
                <DialogHeader>
                    <DialogTitle>Withdraw quote?</DialogTitle>
                    <DialogDescription>
                        Buyers keep a full audit trail. Provide a short reason so they understand why this quote is being pulled back.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-2">
                    <Label htmlFor="withdraw-reason">Reason</Label>
                    <Textarea
                        id="withdraw-reason"
                        rows={4}
                        placeholder="Example: Pricing no longer valid due to material surcharge."
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                        disabled={isProcessing}
                    />
                    <p className="text-xs text-muted-foreground">At least 3 characters.</p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={isProcessing}>
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={handleConfirm}
                        disabled={reason.trim().length < 3 || isProcessing}
                    >
                        Withdraw quote
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}