import { useMemo } from 'react';

import Heading from '@/components/heading';
import { RateLimitRuleEditor } from '@/components/admin/rate-limit-rule-editor';
import { useRateLimits } from '@/hooks/api/admin/use-rate-limits';
import { useUpdateRateLimits } from '@/hooks/api/admin/use-update-rate-limits';
import { useAuth } from '@/contexts/auth-context';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import type { SyncRateLimitPayload } from '@/types/admin';
import type { RateLimitRule } from '@/sdk';

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
