const PLATFORM_ROLES = new Set(['platform_super', 'platform_support']);

export function isPlatformRole(role?: string | null): boolean {
    if (!role) {
        return false;
    }
    return PLATFORM_ROLES.has(role);
}

export { PLATFORM_ROLES };
