import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import { api, buildQuery, type ApiError } from '@/lib/api';
import { toCursorMeta, type CursorPaginationMeta } from '@/lib/pagination';
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
    items?: ApiRoleTemplate[] | null;
    roles?: ApiRoleTemplate[] | null;
    permission_groups?: ApiPermissionGroup[] | null;
    meta?: Record<string, unknown> | null;
}

interface RoleTemplateQueryResult extends RoleTemplateCollection {
    meta?: CursorPaginationMeta;
}

export interface UseCompanyRoleTemplatesParams {
    cursor?: string | null;
    perPage?: number;
}

function mapRoleTemplate(payload: ApiRoleTemplate): RoleTemplate {
    return {
        id: String(payload.id ?? payload.slug),
        slug: payload.slug,
        name: payload.name,
        description: payload.description ?? null,
        permissions: Array.isArray(payload.permissions)
            ? payload.permissions
            : [],
        isSystem: Boolean(payload.is_system),
    };
}

function mapPermissionDefinition(
    payload: ApiPermissionDefinition,
): PermissionDefinition {
    return {
        key: payload.key ?? '',
        label: payload.label ?? payload.key ?? '',
        description: payload.description ?? null,
        level: payload.level ?? null,
        domain: payload.domain ?? null,
    };
}

function mapPermissionGroup(payload: ApiPermissionGroup): PermissionGroup {
    return {
        id: payload.id ?? '',
        label: payload.label ?? payload.id ?? '',
        description: payload.description ?? null,
        permissions: Array.isArray(payload.permissions)
            ? payload.permissions.map(mapPermissionDefinition)
            : [],
    };
}

function normalizeResponse(
    response: RoleTemplateResponse,
): RoleTemplateQueryResult {
    const source = Array.isArray(response.items)
        ? response.items
        : response.roles;

    const roles = Array.isArray(source) ? source.map(mapRoleTemplate) : [];
    const permissionGroups = Array.isArray(response.permission_groups)
        ? response.permission_groups.map(mapPermissionGroup)
        : [];

    return { roles, permissionGroups, meta: toCursorMeta(response.meta) };
}

export function useCompanyRoleTemplates(
    params: UseCompanyRoleTemplatesParams = {},
): UseQueryResult<RoleTemplateQueryResult, ApiError> {
    const query = buildQuery({
        cursor: params.cursor ?? undefined,
        per_page: params.perPage,
    });

    return useQuery<RoleTemplateQueryResult, ApiError>({
        queryKey: queryKeys.companyRoleTemplates.list({
            cursor: params.cursor ?? null,
            perPage: params.perPage ?? null,
        }),
        queryFn: async () => {
            const response = await api.get<RoleTemplateResponse>(
                `/company-role-templates${query}`,
            );
            return normalizeResponse(response as RoleTemplateResponse);
        },
        staleTime: 30_000,
    });
}
