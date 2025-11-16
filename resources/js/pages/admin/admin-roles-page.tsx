import { useMemo } from 'react';

import Heading from '@/components/heading';
import { RoleEditor } from '@/components/admin/role-editor';
import { useRoles } from '@/hooks/api/admin/use-roles';
import { useUpdateRole } from '@/hooks/api/admin/use-update-role';
import { useAuth } from '@/contexts/auth-context';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';

export function AdminRolesPage() {
    const { isAdmin } = useAuth();
    const { data, isLoading } = useRoles();
    const updateRole = useUpdateRole();

    const payloadSignature = useMemo(() => JSON.stringify(data ?? null), [data]);

    if (!isAdmin) {
        return <AccessDeniedPage />;
    }

    const handleSave = async (roleId: string, permissions: string[]) => {
        await updateRole.mutateAsync({ roleId, permissions });
    };

    const savingRoleId = updateRole.isPending ? updateRole.variables?.roleId ?? null : null;

    return (
        <div className="space-y-8">
            <Heading
                title="Roles & permissions"
                description="Update role templates and keep at least one admin scope assigned."
            />

            <RoleEditor
                key={payloadSignature}
                payload={data}
                isLoading={isLoading}
                onSave={handleSave}
                savingRoleId={savingRoleId}
            />
        </div>
    );
}
