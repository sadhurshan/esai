<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\RoleTemplateResource;
use App\Models\RoleTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyRoleTemplateController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $perPage = $this->perPage($request, 25, 100);

        $paginator = RoleTemplate::query()
            ->whereNotIn('slug', ['platform_admin', 'platform_super', 'platform_support'])
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $paginated = $this->paginate($paginator, $request, RoleTemplateResource::class);

        $payload = [
            'roles' => $paginated['items'],
            'permission_groups' => config('rbac.permission_groups', []),
        ];

        return $this->ok($payload, 'Role templates retrieved.', $paginated['meta']);
    }
}
