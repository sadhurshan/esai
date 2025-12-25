<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ActivePersonaContext;
use App\Support\ApiResponse;
use App\Support\Permissions\PermissionRegistry;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAiAdminAccess
{
    private const ADMIN_ROLES = ['owner', 'buyer_admin'];
    private const REQUIRED_PERMISSIONS = ['ai.admin'];

    public function __construct(private readonly PermissionRegistry $permissions)
    {
    }

    public function handle(Request $request, Closure $next): JsonResponse|Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error('Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isPlatformAdmin()) {
            return $next($request);
        }

        $companyId = $this->resolveCompanyId($user);

        if ($companyId === null) {
            return ApiResponse::error('Company context required.', Response::HTTP_FORBIDDEN);
        }

        if ($this->hasAdminRole($user)) {
            return $next($request);
        }

        if ($this->permissions->userHasAny($user, self::REQUIRED_PERMISSIONS, $companyId)) {
            return $next($request);
        }

        return ApiResponse::error('AI admin permission required.', Response::HTTP_FORBIDDEN);
    }

    private function resolveCompanyId(User $user): ?int
    {
        $personaCompanyId = ActivePersonaContext::companyId();

        if ($personaCompanyId !== null) {
            return $personaCompanyId;
        }

        if ($user->company_id !== null) {
            return (int) $user->company_id;
        }

        return null;
    }

    private function hasAdminRole(User $user): bool
    {
        $personaRole = ActivePersonaContext::get()?->role();

        if ($personaRole !== null && in_array($personaRole, self::ADMIN_ROLES, true)) {
            return true;
        }

        $userRole = $user->role;

        return $userRole !== null && in_array($userRole, self::ADMIN_ROLES, true);
    }
}