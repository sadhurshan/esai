import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, type ApiError } from '@/lib/api';
import { queryKeys } from '@/lib/queryKeys';
import type {
    PermissionDefinition,
    PermissionGroup,
    RoleTemplate,
    RoleTemplateCollection,
} from '@/types/rbac';

interface ApiRoleTemplate {
    id?: string | number;
    slug: string;
    name: string;
    description?: string | null;
    permissions?: string[] | null;
    is_system?: boolean | null;
}

interface ApiPermissionDefinition {
    key?: string | null;
    label?: string | null;
    description?: string | null;
    level?: string | null;
    domain?: string | null;
}

interface ApiPermissionGroup {
    id?: string | null;
    label?: string | null;
    description?: string | null;
    permissions?: ApiPermissionDefinition[] | null;
}

interface RoleTemplateResponse {
    roles?: ApiRoleTemplate[] | null;
    permission_groups?: ApiPermissionGroup[] | null;
}

function mapRoleTemplate(payload: ApiRoleTemplate): RoleTemplate {
    return {
        id: String(payload.id ?? payload.slug),
        slug: payload.slug,
        name: payload.name,
        description: payload.description ?? null,
        permissions: Array.isArray(payload.permissions) ? payload.permissions : [],
        isSystem: Boolean(payload.is_system),
    };
}

function mapPermissionDefinition(payload: ApiPermissionDefinition): PermissionDefinition {
    return {
        key: payload.key ?? '',
        label: payload.label ?? (payload.key ?? ''),
        description: payload.description ?? null,
        level: payload.level ?? null,
        domain: payload.domain ?? null,
    };
}

function mapPermissionGroup(payload: ApiPermissionGroup): PermissionGroup {
    return {
        id: payload.id ?? '',
        label: payload.label ?? (payload.id ?? ''),
        description: payload.description ?? null,
        permissions: Array.isArray(payload.permissions)
            ? payload.permissions.map(mapPermissionDefinition)
            : [],
    };
}

function normalizeResponse(response: RoleTemplateResponse): RoleTemplateCollection {
    const roles = Array.isArray(response.roles) ? response.roles.map(mapRoleTemplate) : [];
    const permissionGroups = Array.isArray(response.permission_groups)
        ? response.permission_groups.map(mapPermissionGroup)
        : [];

    return { roles, permissionGroups };
}

export function useCompanyRoleTemplates(): UseQueryResult<RoleTemplateCollection, ApiError> {
    return useQuery<RoleTemplateCollection, ApiError>({
        queryKey: queryKeys.companyRoleTemplates.list(),
        queryFn: async () => {
            const response = (await api.get<RoleTemplateResponse>('/company-role-templates')) as unknown as RoleTemplateResponse;
            return normalizeResponse(response);
        },
        staleTime: 30_000,
    });
}
