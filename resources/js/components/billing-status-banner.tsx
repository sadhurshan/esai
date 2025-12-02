import { Button } from '@/components/ui/button';
import { useAuth } from '@/contexts/auth-context';
import { useFormatting } from '@/contexts/formatting-context';
import { cn } from '@/lib/utils';
import { AlertTriangle, Lock } from 'lucide-react';
import { useNavigate } from 'react-router-dom';

export function BillingStatusBanner() {
    const { state } = useAuth();
    const { formatDate } = useFormatting();
    const navigate = useNavigate();

    const company = state.company;

    if (!company) {
        return null;
    }

    const status = company.billing_status ?? 'inactive';
    const isReadOnly = Boolean(company.billing_read_only);
    const graceEndsAt = (company.billing_grace_ends_at ?? company.billing_lock_at ?? null) as string | null;

    if (status !== 'past_due' && status !== 'cancelled') {
        return null;
    }

    const Icon = isReadOnly ? AlertTriangle : Lock;
    const formattedDeadline = graceEndsAt ? formatDate(graceEndsAt, { dateStyle: 'medium' }) : null;
    const title = isReadOnly
        ? 'Payment past due – workspace is read-only'
        : 'Workspace locked until payment is resolved';
    const description = isReadOnly
        ? formattedDeadline
            ? `Write access will lock on ${formattedDeadline} if payment is not resolved.`
            : 'Write access is temporarily paused while we retry your payment.'
        : 'Grace period has expired and write access is blocked. Update your billing details to resume work.';

    return (
        <div
            className={cn(
                'flex flex-wrap items-center justify-between gap-4 border-b px-4 py-3 text-sm',
                isReadOnly
                    ? 'border-amber-200 bg-amber-50 text-amber-950'
                    : 'border-destructive/40 bg-destructive/10 text-destructive-foreground',
            )}
        >
            <div className="flex items-start gap-3">
                <Icon className="mt-0.5 h-4 w-4 flex-shrink-0" />
                <div>
                    <p className="font-medium">{title}</p>
                    <p className="text-xs opacity-90">{description}</p>
                </div>
            </div>
            <div className="flex items-center gap-2">
                <Button size="sm" variant={isReadOnly ? 'secondary' : 'destructive'} onClick={() => navigate('/app/settings/billing')}>
                    Manage billing
                </Button>
            </div>
        </div>
    );
}
