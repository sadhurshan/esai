import { useMemo, useState } from 'react';

import { FeatureMatrixEditor } from '@/components/admin/feature-matrix-editor';
import Heading from '@/components/heading';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { useAuth } from '@/contexts/auth-context';
import { usePlans } from '@/hooks/api/admin/use-plans';
import { useUpdatePlan } from '@/hooks/api/admin/use-update-plan';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import type { AdminPlansUpdateRequest, Plan } from '@/sdk';

interface PendingPlanUpdate {
    plan: Plan;
    payload: AdminPlansUpdateRequest;
}

const LIMIT_KEYS: Array<keyof Pick<Plan, 'rfqsPerMonth' | 'usersMax'>> = [
    'rfqsPerMonth',
    'usersMax',
];
const EMPTY_PLANS: Plan[] = [];

export function AdminPlansPage() {
    const { isAdmin } = useAuth();
    const { data, isLoading } = usePlans();
    const updatePlan = useUpdatePlan();
    const [pending, setPending] = useState<PendingPlanUpdate | null>(null);

    const plans = data?.items ?? EMPTY_PLANS;
    const planSignature = useMemo(() => JSON.stringify(plans), [plans]);
    const savingPlanId = updatePlan.isPending
        ? (updatePlan.variables?.planId ?? null)
        : null;

    const confirmCopy = useMemo(() => {
        if (!pending) {
            return null;
        }
        return `Lowering RFQ or seat limits can lock existing users out. Continue updating ${pending.plan.name}?`;
    }, [pending]);

    if (!isAdmin) {
        return <AccessDeniedPage />;
    }

    const handleSavePlan = async (
        planId: number,
        payload: AdminPlansUpdateRequest,
    ) => {
        const targetPlan = plans.find((plan) => plan.id === planId);
        if (!targetPlan || Object.keys(payload).length === 0) {
            return;
        }

        if (reducesLimits(targetPlan, payload)) {
            setPending({ plan: targetPlan, payload });
            return;
        }

        await updatePlan.mutateAsync({ planId, payload });
    };

    const handleConfirm = async () => {
        if (!pending) {
            return;
        }
        await updatePlan.mutateAsync({
            planId: pending.plan.id,
            payload: pending.payload,
        });
        setPending(null);
    };

    return (
        <div className="space-y-8">
            <Heading
                title="Plans & feature matrix"
                description="Map billing plans to feature flags, seats, and RFQ throughput."
            />

            <FeatureMatrixEditor
                key={planSignature}
                plans={plans}
                isLoading={isLoading}
                savingPlanId={savingPlanId}
                onSavePlan={handleSavePlan}
            />

            <ConfirmDialog
                open={Boolean(pending)}
                onOpenChange={(open) => setPending(open ? pending : null)}
                title="Lower plan limits?"
                description={confirmCopy ?? ''}
                confirmLabel="Apply changes"
                onConfirm={handleConfirm}
                isProcessing={updatePlan.isPending}
            />
        </div>
    );
}

function reducesLimits(plan: Plan, payload: AdminPlansUpdateRequest) {
    const record = payload as Record<string, unknown>;
    return LIMIT_KEYS.some((key) => {
        if (!(key in record)) {
            return false;
        }
        const next = record[key];
        if (typeof next !== 'number') {
            return false;
        }
        const current = plan[key];
        if (typeof current !== 'number') {
            return false;
        }
        return next < current;
    });
}
