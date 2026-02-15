import { useState } from 'react';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type {
    AdminPermissionGroup,
    AdminRole,
    AdminRolesPayload,
} from '@/types/admin';

const ADMIN_PERMISSION_KEY = 'admin.console';

export interface RoleEditorProps {
    payload?: AdminRolesPayload;
    isLoading?: boolean;
    onSave: (roleId: string, permissions: string[]) => Promise<void> | void;
    savingRoleId?: string | null;
}

export function RoleEditor({
    payload,
    isLoading = false,
    onSave,
    savingRoleId,
}: RoleEditorProps) {
    const roles = payload?.roles ?? [];
    const permissionGroups = payload?.permissionGroups ?? [];
    const [draftPermissions, setDraftPermissions] = useState<
        Record<string, string[]>
    >(() => {
        if (!payload?.roles) {
            return {};
        }
        return payload.roles.reduce<Record<string, string[]>>((acc, role) => {
            acc[role.id] = [...role.permissions];
            return acc;
        }, {});
    });
    const [roleAlerts, setRoleAlerts] = useState<Record<string, string | null>>(
        {},
    );

    const savingState = savingRoleId ?? null;

    if (isLoading) {
        return <LoadingState />;
    }

    if (!roles.length) {
        return (
            <Alert>
                <AlertDescription>
                    No role templates found. Seed role templates to continue.
                </AlertDescription>
            </Alert>
        );
    }

    const getPermissions = (role: AdminRole) =>
        draftPermissions[role.id] ?? role.permissions;

    const hasChanges = (role: AdminRole) => {
        const current = new Set(getPermissions(role));
        if (current.size !== role.permissions.length) {
            return true;
        }

        return role.permissions.some((permission) => !current.has(permission));
    };

    const handleSave = async (role: AdminRole) => {
        const permissions = getPermissions(role);
        if (!hasChanges(role)) {
            return;
        }
        await onSave(role.id, permissions);
    };

    const updateRolePermissions = (
        role: AdminRole,
        mutator: (draft: Set<string>) => void,
    ) => {
        setDraftPermissions((prev) => {
            const next = new Set(prev[role.id] ?? role.permissions);
            mutator(next);
            return {
                ...prev,
                [role.id]: Array.from(next).sort(),
            };
        });
        setRoleAlerts((prev) => ({
            ...prev,
            [role.id]: null,
        }));
    };

    const wouldRemoveAdminAccess = (role: AdminRole) => {
        const current = new Set(getPermissions(role));
        if (!current.has(ADMIN_PERMISSION_KEY)) {
            return false;
        }

        const remainingRoles = roles.filter(
            (candidate) => candidate.id !== role.id,
        );
        const otherHasAdmin = remainingRoles.some((candidate) => {
            const perms = new Set(getPermissions(candidate));
            return perms.has(ADMIN_PERMISSION_KEY);
        });

        return !otherHasAdmin;
    };

    const handleTogglePermission = (
        role: AdminRole,
        permissionKey: string,
        checked: boolean,
    ) => {
        if (
            !checked &&
            permissionKey === ADMIN_PERMISSION_KEY &&
            wouldRemoveAdminAccess(role)
        ) {
            setRoleAlerts((prev) => ({
                ...prev,
                [role.id]:
                    'At least one role must retain Admin Console access.',
            }));
            return;
        }

        updateRolePermissions(role, (draft) => {
            if (checked) {
                draft.add(permissionKey);
            } else {
                draft.delete(permissionKey);
            }
        });
    };

    const handleClearGroup = (role: AdminRole, group: AdminPermissionGroup) => {
        const protectAdmin =
            group.permissions.some(
                (permission) => permission.key === ADMIN_PERMISSION_KEY,
            ) && wouldRemoveAdminAccess(role);

        if (protectAdmin) {
            setRoleAlerts((prev) => ({
                ...prev,
                [role.id]:
                    'Admin Console access cannot be removed from every role.',
            }));
        }

        updateRolePermissions(role, (draft) => {
            group.permissions.forEach((permission) => {
                if (permission.key === ADMIN_PERMISSION_KEY && protectAdmin) {
                    return;
                }
                draft.delete(permission.key);
            });
        });
    };

    const handleSelectGroup = (
        role: AdminRole,
        group: AdminPermissionGroup,
    ) => {
        updateRolePermissions(role, (draft) => {
            group.permissions.forEach((permission) =>
                draft.add(permission.key),
            );
        });
    };

    const handleResetRole = (role: AdminRole) => {
        setDraftPermissions((prev) => ({
            ...prev,
            [role.id]: [...role.permissions],
        }));
        setRoleAlerts((prev) => ({
            ...prev,
            [role.id]: null,
        }));
    };

    return (
        <div className="space-y-6">
            {roles.map((role) => {
                const permissions = new Set(getPermissions(role));
                const roleAlert = roleAlerts[role.id];
                const dirty = hasChanges(role);
                const isSaving = savingState === role.id;

                return (
                    <Card
                        key={role.id}
                        className="border border-muted-foreground/20"
                    >
                        <CardHeader>
                            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2 text-lg">
                                        {role.name}
                                        {role.isSystem ? (
                                            <Badge variant="outline">
                                                System
                                            </Badge>
                                        ) : null}
                                    </CardTitle>
                                    {role.description ? (
                                        <CardDescription>
                                            {role.description}
                                        </CardDescription>
                                    ) : (
                                        <CardDescription>
                                            Slug: {role.slug}
                                        </CardDescription>
                                    )}
                                </div>
                                <div className="flex flex-wrap gap-2 text-sm text-muted-foreground">
                                    <span>
                                        {permissions.size} scopes enabled
                                    </span>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {permissionGroups.map((group) => (
                                <div
                                    key={`${role.id}-${group.id}`}
                                    className="rounded-lg border bg-muted/30 p-4"
                                >
                                    <div className="flex flex-col gap-2 pb-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <p className="font-medium text-foreground">
                                                {group.label}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {group.description}
                                            </p>
                                        </div>
                                        <div className="flex gap-2">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    handleSelectGroup(
                                                        role,
                                                        group,
                                                    )
                                                }
                                            >
                                                Select all
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    handleClearGroup(
                                                        role,
                                                        group,
                                                    )
                                                }
                                            >
                                                Clear
                                            </Button>
                                        </div>
                                    </div>
                                    <div className="grid gap-3 md:grid-cols-2">
                                        {group.permissions.map((permission) => {
                                            const checked = permissions.has(
                                                permission.key,
                                            );

                                            return (
                                                <label
                                                    key={`${role.id}-${permission.key}`}
                                                    className={cn(
                                                        'flex items-start gap-3 rounded-md border bg-background p-3 text-sm transition',
                                                        checked
                                                            ? 'border-primary/60 shadow-sm'
                                                            : 'border-transparent',
                                                    )}
                                                >
                                                    <Checkbox
                                                        checked={checked}
                                                        onCheckedChange={(
                                                            value,
                                                        ) =>
                                                            handleTogglePermission(
                                                                role,
                                                                permission.key,
                                                                Boolean(value),
                                                            )
                                                        }
                                                        aria-label={`Toggle ${permission.label} for ${role.name}`}
                                                    />
                                                    <div className="flex-1 space-y-1">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium text-foreground">
                                                                {
                                                                    permission.label
                                                                }
                                                            </span>
                                                            <Badge
                                                                variant="outline"
                                                                className="text-[10px] uppercase"
                                                            >
                                                                {
                                                                    permission.level
                                                                }
                                                            </Badge>
                                                        </div>
                                                        <p className="text-xs text-muted-foreground">
                                                            {
                                                                permission.description
                                                            }
                                                        </p>
                                                    </div>
                                                </label>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                        <CardFooter className="flex flex-col gap-4 border-t bg-muted/30 p-4 sm:flex-row sm:items-center sm:justify-between">
                            {roleAlert ? (
                                <Alert
                                    variant="destructive"
                                    className="w-full sm:max-w-md"
                                >
                                    <AlertDescription>
                                        {roleAlert}
                                    </AlertDescription>
                                </Alert>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Changes are auto-staged. Save updates to
                                    persist them for every tenant using this
                                    role.
                                </p>
                            )}
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={!dirty || isSaving}
                                    onClick={() => handleResetRole(role)}
                                >
                                    Reset
                                </Button>
                                <Button
                                    type="button"
                                    disabled={!dirty || isSaving}
                                    onClick={() => handleSave(role)}
                                >
                                    {isSaving ? 'Saving...' : 'Save changes'}
                                </Button>
                            </div>
                        </CardFooter>
                    </Card>
                );
            })}
        </div>
    );
}

function LoadingState() {
    return (
        <div className="space-y-4">
            {Array.from({ length: 2 }).map((_, index) => (
                <div
                    key={index}
                    className="space-y-3 rounded-xl border border-dashed border-muted p-4"
                >
                    <Skeleton className="h-5 w-40" />
                    <Skeleton className="h-4 w-full" />
                    <div className="grid gap-3 md:grid-cols-2">
                        <Skeleton className="h-16 w-full" />
                        <Skeleton className="h-16 w-full" />
                    </div>
                </div>
            ))}
        </div>
    );
}
