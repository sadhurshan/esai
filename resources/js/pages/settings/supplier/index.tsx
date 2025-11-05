import HeadingSmall from '@/components/heading-small';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { useSupplierSelfStatus, useUpdateSupplierVisibility, type DirectoryVisibility, type SupplierSelfStatus } from '@/hooks/api/useSupplierSelfService';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Supplier Settings',
        href: '/settings/supplier',
    },
];

type Props = {
    supplierStatus: string;
    directoryVisibility: DirectoryVisibility;
    supplierProfileCompletedAt: string | null;
    isSupplierApproved: boolean;
    isSupplierListed: boolean;
    canToggleVisibility: boolean;
};

const statusCopy: Record<string, { title: string; description: string; variant: 'default' | 'warning' | 'destructive' }> = {
    none: {
        title: 'Not Applied',
        description: 'Submit your supplier application to begin the verification process.',
        variant: 'warning',
    },
    pending: {
        title: 'Pending Review',
        description: 'Your application is under review by the platform team.',
        variant: 'warning',
    },
    approved: {
        title: 'Approved Supplier',
        description: 'You can respond to RFQs and manage directory visibility.',
        variant: 'default',
    },
    rejected: {
        title: 'Application Rejected',
        description: 'Update your profile and reapply or contact support for next steps.',
        variant: 'destructive',
    },
    suspended: {
        title: 'Supplier Suspended',
        description: 'Supplier actions are paused. Contact support to reinstate access.',
        variant: 'destructive',
    },
};

export default function SupplierSettingsPage(props: Props) {
    const initialStatus: SupplierSelfStatus = useMemo(
        () => ({
            supplier_status: props.supplierStatus,
            directory_visibility: props.directoryVisibility,
            supplier_profile_completed_at: props.supplierProfileCompletedAt,
            is_listed: props.isSupplierListed,
        }),
        [
            props.directoryVisibility,
            props.isSupplierListed,
            props.supplierProfileCompletedAt,
            props.supplierStatus,
        ],
    );

    const { data, isLoading } = useSupplierSelfStatus(initialStatus);
    const updateVisibility = useUpdateSupplierVisibility();
    const [hasInteracted, setHasInteracted] = useState(false);

    const status = data ?? initialStatus;
    const copy = statusCopy[status.supplier_status] ?? statusCopy.none;

    const profileCompleted = status.supplier_profile_completed_at !== null;
    const supplierApproved = status.supplier_status === 'approved';

    const canToggle =
        props.canToggleVisibility && supplierApproved && profileCompleted && !updateVisibility.isPending;

    const handleToggle = (checked: boolean) => {
        setHasInteracted(true);
        const nextVisibility: DirectoryVisibility = checked ? 'public' : 'private';
        updateVisibility.mutate({ visibility: nextVisibility });
    };

    const visibilityChecked = status.directory_visibility === 'public';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Supplier Settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Supplier directory"
                        description="Control your supplier listing and track verification status."
                    />

                    <Alert variant={copy.variant === 'destructive' ? 'destructive' : copy.variant === 'warning' ? 'default' : 'default'}>
                        <AlertTitle>{copy.title}</AlertTitle>
                        <AlertDescription>{copy.description}</AlertDescription>
                    </Alert>

                    <div className="space-y-4 rounded-md border p-4">
                        <div className="flex items-center justify-between">
                            <div className="space-y-1">
                                <p className="font-medium">Public directory listing</p>
                                <p className="text-sm text-muted-foreground">
                                    When enabled, your company appears in the Supplier Directory for buyers to discover.
                                </p>
                                {!profileCompleted && (
                                    <p className="text-sm text-amber-600">
                                        Complete your supplier profile before listing publicly.
                                    </p>
                                )}
                            </div>
                            <div className="flex items-center gap-3">
                                {updateVisibility.isPending && <Spinner />}
                                <Checkbox
                                    checked={visibilityChecked}
                                    onCheckedChange={(value) => handleToggle(Boolean(value))}
                                    disabled={!canToggle}
                                    aria-label="Toggle supplier directory visibility"
                                />
                            </div>
                        </div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <span>Current visibility:</span>
                            <Badge variant={visibilityChecked ? 'default' : 'secondary'}>
                                {visibilityChecked ? 'Public' : 'Private'}
                            </Badge>
                        </div>
                        {!props.canToggleVisibility && (
                            <p className="text-sm text-muted-foreground">
                                Only company owners and buyer admins can change directory visibility.
                            </p>
                        )}
                        {!supplierApproved && (
                            <p className="text-sm text-muted-foreground">
                                Directory controls unlock once your supplier application is approved.
                            </p>
                        )}
                    </div>

                    <div className="space-y-2 rounded-md border p-4">
                        <p className="font-medium">Supplier company profile</p>
                        <p className="text-sm text-muted-foreground">
                            Review the information buyers see about your capabilities.
                        </p>
                        <Button asChild variant="outline">
                            <Link href="/supplier/company-profile">View profile</Link>
                        </Button>
                    </div>

                    {hasInteracted && updateVisibility.isError && (
                        <Alert variant="destructive">
                            <AlertTitle>Visibility update failed</AlertTitle>
                            <AlertDescription>{updateVisibility.error?.message ?? 'Unable to update visibility.'}</AlertDescription>
                        </Alert>
                    )}

                    {isLoading && (
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Spinner />
                            <span>Loading current supplier status…</span>
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
