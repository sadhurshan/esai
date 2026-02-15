import { useMemo } from 'react';

import { RateLimitRuleEditor } from '@/components/admin/rate-limit-rule-editor';
import Heading from '@/components/heading';
import { useAuth } from '@/contexts/auth-context';
import { useRateLimits } from '@/hooks/api/admin/use-rate-limits';
import { useUpdateRateLimits } from '@/hooks/api/admin/use-update-rate-limits';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import type { RateLimitRule } from '@/sdk';
import type { SyncRateLimitPayload } from '@/types/admin';

const EMPTY_RULES: RateLimitRule[] = [];

export function AdminRateLimitsPage() {
    const { isAdmin } = useAuth();
    const { data, isLoading } = useRateLimits();
    const updateRateLimits = useUpdateRateLimits();

    const rules = data?.items ?? EMPTY_RULES;
    const rateLimitSignature = useMemo(() => JSON.stringify(rules), [rules]);

    if (!isAdmin) {
        return <AccessDeniedPage />;
    }

    const handleSave = async (payload: SyncRateLimitPayload) => {
        await updateRateLimits.mutateAsync(payload);
    };

    return (
        <div className="space-y-8">
            <Heading
                title="Rate limits"
                description="Throttle critical endpoints per scope to protect tenant resources."
            />

            <RateLimitRuleEditor
                key={rateLimitSignature}
                rules={rules}
                isLoading={isLoading}
                isSaving={updateRateLimits.isPending}
                onSave={handleSave}
            />
        </div>
    );
}
