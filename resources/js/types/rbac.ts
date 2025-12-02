export interface PermissionDefinition {
    key: string;
    label: string;
    description?: string | null;
    level?: string | null;
    domain?: string | null;
}

export interface PermissionGroup {
    id: string;
    label: string;
    description?: string | null;
    permissions: PermissionDefinition[];
}

export interface RoleTemplate {
    id: string;
    slug: string;
    name: string;
    description?: string | null;
    permissions: string[];
    isSystem: boolean;
}

export interface RoleTemplateCollection {
    roles: RoleTemplate[];
    permissionGroups: PermissionGroup[];
}
