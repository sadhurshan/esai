import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { show } from '@/routes/plan';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type Props = {
    plan: {
        code: string;
        name: string;
        rfqs_per_month: number;
        users_max: number;
        storage_gb: number;
    } | null;
    status: string;
    trialEndsAt: string | null;
    upgradeUrl: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Plan & billing',
        href: show().url,
    },
];

export default function PlanPage({ plan, status, trialEndsAt, upgradeUrl }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Plan & billing" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Subscription plan"
                        description="Review your current plan and manage billing."
                    />

                    <Card>
                        <CardHeader>
                            <CardTitle>{plan?.name ?? 'No active plan'}</CardTitle>
                            <CardDescription>
                                Status: <span className="capitalize">{status}</span>
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {trialEndsAt && (
                                <p className="text-sm text-muted-foreground">
                                    Trial ends at {new Date(trialEndsAt).toLocaleString()}
                                </p>
                            )}

                            {plan ? (
                                <ul className="grid gap-2 text-sm text-muted-foreground">
                                    <li>Monthly RFQs: {plan.rfqs_per_month === 0 ? 'Unlimited' : plan.rfqs_per_month}</li>
                                    <li>User seats: {plan.users_max === 0 ? 'Unlimited' : plan.users_max}</li>
                                    <li>Storage: {plan.storage_gb === 0 ? 'Unlimited' : `${plan.storage_gb} GB`}</li>
                                </ul>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No plan is currently assigned to your company.
                                </p>
                            )}

                            <Button asChild variant="secondary">
                                <a href={upgradeUrl}>Manage Billing (coming soon)</a>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
