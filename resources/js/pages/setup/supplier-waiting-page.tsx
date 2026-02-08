import { Link } from 'react-router-dom';
import { Clock, ShieldCheck, AlertCircle, Building2 } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useAuth } from '@/contexts/auth-context';
import { useSupplierSelfStatus, type SupplierSelfStatus, type DirectoryVisibility } from '@/hooks/api/useSupplierSelfService';

const STATUS_META = {
    none: {
        label: 'Not submitted',
        description: 'Your supplier application has not been submitted yet.',
        icon: Building2,
        badge: 'secondary' as const,
    },
    pending: {
        label: 'Pending review',
        description: 'Our team is reviewing your supplier application. Expect an update soon.',
        icon: Clock,
        badge: 'outline' as const,
    },
    approved: {
        label: 'Approved',
        description: 'Supplier tooling is active. You can respond to RFQs and manage your profile.',
        icon: ShieldCheck,
        badge: 'default' as const,
    },
    rejected: {
        label: 'Needs updates',
        description: 'The last application was declined. Update your profile and reapply.',
        icon: AlertCircle,
        badge: 'destructive' as const,
    },
    suspended: {
        label: 'Suspended',
        description: 'Supplier access is temporarily disabled. Contact support for next steps.',
        icon: AlertCircle,
        badge: 'destructive' as const,
    },
};

type SupplierStatusKey = keyof typeof STATUS_META;

export function SupplierWaitingPage() {
    const { state } = useAuth();
    const initialStatus: SupplierSelfStatus | undefined = state.company
        ? {
              supplier_status: (state.company.supplier_status ?? 'pending') as string,
              directory_visibility: (state.company.directory_visibility ?? 'private') as DirectoryVisibility,
              supplier_profile_completed_at: state.company.supplier_profile_completed_at ?? null,
              is_listed: Boolean((state.company as { is_listed?: boolean }).is_listed ?? false),
              current_application: null,
          }
        : undefined;

    const supplierStatusQuery = useSupplierSelfStatus(initialStatus);
    const supplierStatusData = supplierStatusQuery.data ?? initialStatus;
    const status = (supplierStatusData?.supplier_status ?? 'pending') as SupplierStatusKey;
    const meta = STATUS_META[status] ?? STATUS_META.pending;
    const StatusIcon = meta.icon;

    return (
        <div className="space-y-6">
            <div>
                <p className="text-sm text-muted-foreground">Supplier onboarding</p>
                <h1 className="text-2xl font-semibold tracking-tight">Supplier approval in progress</h1>
                <p className="text-sm text-muted-foreground">
                    We are reviewing your supplier application. While you wait, you can finish your supplier profile and upload
                    compliance documents.
                </p>
            </div>

            <Card>
                <CardHeader className="space-y-2">
                    <div className="flex items-center gap-2">
                        <StatusIcon className="h-5 w-5 text-muted-foreground" />
                        <CardTitle className="text-lg">Current status</CardTitle>
                    </div>
                    <CardDescription>We will notify you as soon as your supplier account is approved.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex items-center gap-3">
                        <Badge variant={meta.badge}>{meta.label}</Badge>
                        <span className="text-sm text-muted-foreground">{meta.description}</span>
                    </div>

                    <Alert>
                        <AlertDescription>
                            Complete your supplier application and keep documents current to speed up approval.
                        </AlertDescription>
                    </Alert>

                    <div className="flex flex-wrap gap-3">
                        <Button asChild>
                            <Link to="/app/settings">Open supplier application</Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link to="/app/supplier/company-profile">Review supplier profile</Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
