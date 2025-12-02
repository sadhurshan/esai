<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\UpdateRoleTemplateRequest;
use App\Http\Resources\RoleTemplateResource;
use App\Models\RoleTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RoleTemplateController extends ApiController
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', RoleTemplate::class);

        $roles = RoleTemplate::query()->orderBy('name')->get();

        return $this->ok([
            'roles' => RoleTemplateResource::collection($roles)->toArray(request()),
            'permission_groups' => config('rbac.permission_groups'),
        ], 'Roles retrieved.');
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

        return $this->ok([
            'role' => (new RoleTemplateResource($roleTemplate))->toArray($request),
        ], 'Role updated.');
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
