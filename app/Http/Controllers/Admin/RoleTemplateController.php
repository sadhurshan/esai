<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRoleTemplateRequest;
use App\Http\Resources\Admin\RoleTemplateResource;
use App\Models\RoleTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RoleTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', RoleTemplate::class);

        $roles = RoleTemplate::query()->orderBy('name')->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Roles retrieved.',
            'data' => [
                'roles' => RoleTemplateResource::collection($roles),
                'permission_groups' => config('rbac.permission_groups'),
            ],
        ]);
    }

    public function update(UpdateRoleTemplateRequest $request, RoleTemplate $roleTemplate): JsonResponse
    {
        $this->authorize('update', $roleTemplate);

        $permissions = array_values(array_unique($request->input('permissions', [])));
        $this->guardAdminPermissionCoverage($roleTemplate, $permissions);

        $roleTemplate->forceFill([
            'permissions' => $permissions,
        ])->save();

        $roleTemplate->refresh();

        return response()->json([
            'status' => 'success',
            'message' => 'Role updated.',
            'data' => [
                'role' => RoleTemplateResource::make($roleTemplate),
            ],
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function guardAdminPermissionCoverage(RoleTemplate $roleTemplate, array $permissions): void
    {
        $adminPermission = config('rbac.admin_permission_key');
        if (! $adminPermission) {
            return;
        }

        $hasPermission = in_array($adminPermission, $permissions, true);
        if ($hasPermission) {
            return;
        }

        $otherRoleHasPermission = RoleTemplate::query()
            ->whereKeyNot($roleTemplate->getKey())
            ->whereJsonContains('permissions', $adminPermission)
            ->exists();

        if (! $otherRoleHasPermission) {
            throw ValidationException::withMessages([
                'permissions' => __('At least one role must retain the :permission permission.', ['permission' => $adminPermission]),
            ]);
        }
    }
}
