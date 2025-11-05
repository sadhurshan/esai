<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\SupplierVisibilityUpdateRequest;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierSelfServiceController extends ApiController
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function status(Request $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $company = $user->company;

        if ($company === null) {
            return $this->fail('Company context required.', 403);
        }

        return $this->ok([
            'supplier_status' => $company->supplier_status instanceof \BackedEnum ? $company->supplier_status->value : $company->supplier_status,
            'directory_visibility' => $company->directory_visibility,
            'supplier_profile_completed_at' => optional($company->supplier_profile_completed_at)?->toIso8601String(),
            'is_listed' => $company->isSupplierListed(),
        ]);
    }

    public function updateVisibility(SupplierVisibilityUpdateRequest $request): JsonResponse
    {
        $user = $this->resolveRequestUser($request);

        if ($user === null) {
            return $this->fail('Authentication required.', 401);
        }

        $company = $user->company;

        if ($company === null) {
            return $this->fail('Company context required.', 403);
        }

        if (! $company->isSupplierApproved()) {
            return $this->fail('Supplier approval required.', 403);
        }

        if (! in_array($user->role, ['owner', 'buyer_admin'], true) && $user->id !== $company->owner_user_id) {
            return $this->fail('Forbidden.', 403);
        }

        $payload = $request->validated();
        $visibility = $payload['visibility'];

        if ($visibility === 'public' && $company->supplier_profile_completed_at === null) {
            return $this->fail('Complete your supplier profile before listing publicly.', 422);
        }

        $before = $company->getOriginal();
        $company->directory_visibility = $visibility;
        $changes = $company->getDirty();

        if ($changes !== []) {
            $company->save();
            $this->auditLogger->updated($company, $before, $changes);
        }

        return $this->ok([
            'supplier_status' => $company->supplier_status instanceof \BackedEnum ? $company->supplier_status->value : $company->supplier_status,
            'directory_visibility' => $company->directory_visibility,
            'supplier_profile_completed_at' => optional($company->supplier_profile_completed_at)?->toIso8601String(),
            'is_listed' => $company->isSupplierListed(),
        ], 'Supplier directory visibility updated.');
    }
}
