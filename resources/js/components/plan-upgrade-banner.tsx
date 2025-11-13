import { Button } from '@/components/ui/button';
import { X } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '@/contexts/auth-context';

export function PlanUpgradeBanner() {
    const { state, clearPlanLimit } = useAuth();
    const navigate = useNavigate();

    if (!state.planLimit) {
        return null;
    }

    const message =
        state.planLimit.message ??
        'You have reached the current plan limit for this feature. Upgrade to continue.';

    return (
        <div className="flex items-center justify-between gap-4 border-b border-dashed border-brand-accent bg-brand-background/70 px-4 py-2 text-sm text-brand-primary">
            <div className="flex flex-col">
                <span className="font-medium">Upgrade Required</span>
                <span className="text-xs text-muted-foreground">{message}</span>
            </div>
            <div className="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => navigate('/app/settings?tab=billing')}
                >
                    View plans
                </Button>
                <Button variant="ghost" size="icon" onClick={() => clearPlanLimit()}>
                    <X className="h-4 w-4" />
                    <span className="sr-only">Dismiss upgrade banner</span>
                </Button>
            </div>
        </div>
    );
}
