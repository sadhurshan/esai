import { RefreshCcw, Shield, ShieldAlert, ShieldQuestion } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';

import { EmptyState } from '@/components/empty-state';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuth } from '@/contexts/auth-context';
import { useCompanyRoleTemplates } from '@/hooks/api/useCompanyRoleTemplates';
import { cn } from '@/lib/utils';
import { AccessDeniedPage } from '@/pages/errors/access-denied-page';
import type { PermissionDefinition, RoleTemplate } from '@/types/rbac';

const LEVEL_BADGE_VARIANTS: Record<
    string,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    read: 'secondary',
    write: 'default',
    admin: 'destructive',
};

type PermissionMeta = PermissionDefinition & { groupLabel?: string | null };

export function CompanyRolesPage() {
    const { state, isAdmin } = useAuth();
    const userRole = state.user?.role ?? null;
    const isOwner = userRole === 'owner';
    const canViewRoles = isOwner || isAdmin;

    const roleTemplatesQuery = useCompanyRoleTemplates();
    const [selectedRole, setSelectedRole] = useState<string | null>(null);

    const roles = useMemo(
        () => roleTemplatesQuery.data?.roles ?? [],
        [roleTemplatesQuery.data],
    );
    const permissionGroups = useMemo(
        () => roleTemplatesQuery.data?.permissionGroups ?? [],
        [roleTemplatesQuery.data],
    );

    const permissionLookup = useMemo(() => {
        const map = new Map<string, PermissionMeta>();
        permissionGroups.forEach((group) => {
            group.permissions.forEach((permission) => {
                map.set(permission.key, {
                    ...permission,
                    groupLabel: group.label,
                });
            });
        });
        return map;
    }, [permissionGroups]);

    const effectiveSelectedRole = useMemo(() => {
        if (!roles.length) {
            return null;
        }

        if (selectedRole && roles.some((role) => role.slug === selectedRole)) {
            return selectedRole;
        }

        return roles[0]?.slug ?? null;
    }, [roles, selectedRole]);

    const selectedRoleTemplate = useMemo(
        () => roles.find((role) => role.slug === effectiveSelectedRole) ?? null,
        [roles, effectiveSelectedRole],
    );

    const stats = useMemo(() => {
        const uniquePermissions = new Set<string>();
        roles.forEach((role) => {
            role.permissions.forEach((permission) =>
                uniquePermissions.add(permission),
            );
        });

        return {
            roleCount: roles.length,
            permissionCount: uniquePermissions.size,
        };
    }, [roles]);

    if (!canViewRoles) {
        return <AccessDeniedPage />;
    }

    return (
        <div className="space-y-6">
            <Helmet>
                <title>Role definitions · Elements Supply</title>
            </Helmet>
            <div>
                <p className="text-sm text-muted-foreground">
                    Workspace · Settings
                </p>
                <h1 className="text-2xl font-semibold tracking-tight">
                    Role definitions
                </h1>
                <p className="text-sm text-muted-foreground">
                    Review the permissions each seeded role template includes
                    before inviting new teammates.
                </p>
            </div>
            <Alert>
                <Shield className="h-5 w-5" />
                <AlertTitle>Owners or buyer admins only</AlertTitle>
                <AlertDescription>
                    Use role descriptions and permission scopes below when
                    deciding which access level to grant. Editing templates is
                    limited to platform operators.
                </AlertDescription>
            </Alert>
            <div className="grid gap-4 sm:grid-cols-2">
                <Card>
                    <CardContent className="space-y-1 p-4">
                        <p className="text-sm text-muted-foreground">
                            Seeded roles
                        </p>
                        <p className="text-2xl font-semibold">
                            {stats.roleCount}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Owner, buyer, supplier, and finance templates
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="space-y-1 p-4">
                        <p className="text-sm text-muted-foreground">
                            Unique permissions
                        </p>
                        <p className="text-2xl font-semibold">
                            {stats.permissionCount}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Across sourcing, suppliers, orders, billing,
                            analytics, and workspace
                        </p>
                    </CardContent>
                </Card>
            </div>
            <div className="flex flex-wrap items-center gap-3">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => roleTemplatesQuery.refetch()}
                    disabled={roleTemplatesQuery.isFetching}
                    className="inline-flex items-center gap-2"
                >
                    <RefreshCcw className="h-4 w-4" /> Refresh
                </Button>
                {roleTemplatesQuery.isFetching ? (
                    <span className="text-xs text-muted-foreground">
                        Updating…
                    </span>
                ) : null}
            </div>
            <div className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Role catalog</CardTitle>
                        <CardDescription>
                            Select a role to review its permissions.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {roleTemplatesQuery.isLoading ? (
                            <RoleListSkeleton />
                        ) : roleTemplatesQuery.isError ? (
                            <Alert variant="destructive">
                                <AlertTitle>
                                    Unable to load role templates
                                </AlertTitle>
                                <AlertDescription>
                                    {roleTemplatesQuery.error?.message ??
                                        'Please try again later.'}
                                </AlertDescription>
                            </Alert>
                        ) : !roles.length ? (
                            <EmptyState
                                title="No roles found"
                                description="Role templates will appear after your administrator seeds RBAC definitions."
                                icon={<ShieldAlert className="h-8 w-8" />}
                            />
                        ) : (
                            <div className="grid gap-4 lg:grid-cols-[0.55fr_0.45fr]">
                                <div className="space-y-3">
                                    {roles.map((role) => (
                                        <RoleListItem
                                            key={role.slug}
                                            role={role}
                                            isActive={
                                                effectiveSelectedRole ===
                                                role.slug
                                            }
                                            onSelect={() =>
                                                setSelectedRole(role.slug)
                                            }
                                        />
                                    ))}
                                </div>
                                {selectedRoleTemplate ? (
                                    <RolePermissionPanel
                                        role={selectedRoleTemplate}
                                        permissionLookup={permissionLookup}
                                    />
                                ) : null}
                            </div>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Permission catalog</CardTitle>
                        <CardDescription>
                            See how permissions map to feature domains.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {roleTemplatesQuery.isLoading ? (
                            <PermissionGroupSkeleton />
                        ) : roleTemplatesQuery.isError ? (
                            <Alert variant="destructive">
                                <AlertTitle>
                                    Unable to load permissions
                                </AlertTitle>
                                <AlertDescription>
                                    {roleTemplatesQuery.error?.message ??
                                        'Please try again later.'}
                                </AlertDescription>
                            </Alert>
                        ) : !permissionGroups.length ? (
                            <EmptyState
                                title="No permission groups"
                                description="Permission metadata is required to explain each role scope."
                                icon={<ShieldQuestion className="h-8 w-8" />}
                            />
                        ) : (
                            permissionGroups.map((group) => (
                                <div
                                    key={group.id}
                                    className="rounded-lg border p-4"
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <p className="text-sm font-medium">
                                                {group.label}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {group.description ??
                                                    'No description provided.'}
                                            </p>
                                        </div>
                                        <Badge variant="outline">
                                            {group.permissions.length}{' '}
                                            permissions
                                        </Badge>
                                    </div>
                                    <ul className="mt-3 space-y-2">
                                        {group.permissions.map((permission) => (
                                            <li
                                                key={permission.key}
                                                className="rounded-md bg-muted/40 p-2"
                                            >
                                                <div className="flex items-center justify-between gap-2">
                                                    <p className="text-sm font-medium">
                                                        {permission.label}
                                                    </p>
                                                    {permission.level ? (
                                                        <Badge
                                                            variant={
                                                                LEVEL_BADGE_VARIANTS[
                                                                    permission
                                                                        .level
                                                                ] ?? 'secondary'
                                                            }
                                                        >
                                                            {permission.level}
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    {permission.description ??
                                                        'No description provided.'}
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

interface RoleListItemProps {
    role: RoleTemplate;
    isActive: boolean;
    onSelect: () => void;
}

function RoleListItem({ role, isActive, onSelect }: RoleListItemProps) {
    const permissionCount = role.permissions.length;

    return (
        <button
            type="button"
            onClick={onSelect}
            className={cn(
                'w-full rounded-lg border p-4 text-left transition hover:border-primary',
                isActive
                    ? 'border-primary bg-primary/5'
                    : 'border-border bg-card',
            )}
        >
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm font-semibold">{role.name}</p>
                    <p className="text-xs text-muted-foreground">
                        {role.description ?? 'No description provided.'}
                    </p>
                </div>
                <Badge variant="outline">{permissionCount} perms</Badge>
            </div>
        </button>
    );
}

interface RolePermissionPanelProps {
    role: RoleTemplate;
    permissionLookup: Map<
        string,
        PermissionDefinition & { groupLabel?: string | null }
    >;
}

function RolePermissionPanel({
    role,
    permissionLookup,
}: RolePermissionPanelProps) {
    const permissions = role.permissions;

    if (!permissions.length) {
        return (
            <div className="rounded-lg border p-4">
                <p className="text-sm font-medium">No permissions assigned</p>
                <p className="text-xs text-muted-foreground">
                    This template currently has an empty permission set.
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-3">
            <div className="rounded-lg border p-4">
                <p className="text-sm font-medium">{role.name} permissions</p>
                <p className="text-xs text-muted-foreground">
                    {role.permissions.length} total scopes
                </p>
            </div>
            <div className="max-h-[32rem] space-y-2 overflow-y-auto pr-1">
                {permissions.map((permissionKey) => {
                    const meta = permissionLookup.get(permissionKey);
                    const label = meta?.label ?? permissionKey;
                    const description =
                        meta?.description ?? 'No description provided.';
                    const level = meta?.level ?? 'custom';
                    const groupLabel = meta?.groupLabel ?? 'Other';
                    const levelVariant =
                        LEVEL_BADGE_VARIANTS[level] ?? 'outline';

                    return (
                        <div
                            key={permissionKey}
                            className="rounded-lg border p-3"
                        >
                            <div className="flex items-center justify-between gap-2">
                                <div>
                                    <p className="text-sm font-semibold">
                                        {label}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {groupLabel}
                                    </p>
                                </div>
                                <Badge
                                    variant={levelVariant}
                                    className="capitalize"
                                >
                                    {level}
                                </Badge>
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function RoleListSkeleton() {
    return (
        <div className="grid gap-4 lg:grid-cols-[0.55fr_0.45fr]">
            <div className="space-y-3">
                {[0, 1, 2, 3].map((row) => (
                    <Skeleton key={row} className="h-20 w-full" />
                ))}
            </div>
            <Skeleton className="h-96 w-full" />
        </div>
    );
}

function PermissionGroupSkeleton() {
    return (
        <div className="space-y-4">
            {[0, 1, 2].map((row) => (
                <Skeleton key={row} className="h-40 w-full" />
            ))}
        </div>
    );
}
