<?php

namespace App\Http\Resources;

use Carbon\CarbonInterface;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class CompanyMemberResource extends JsonResource
{
    private const BUYER_ROLES = ['owner', 'buyer_admin', 'buyer_member', 'buyer_requester', 'finance'];
    private const SUPPLIER_ROLES = ['supplier_admin', 'supplier_estimator'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->membership_role ?? $this->role,
            'job_title' => $this->job_title,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url ?? null,
            'last_login_at' => $this->formatDateTime($this->last_login_at),
            'is_active_company' => $this->isActiveForMembership(),
            'membership' => [
                'id' => $this->membership_id ?? null,
                'company_id' => $this->membership_company_id ?? null,
                'is_default' => (bool) ($this->membership_is_default ?? false),
                'last_used_at' => $this->formatDateTime($this->membership_last_used_at ?? null),
                'created_at' => $this->formatDateTime($this->membership_created_at ?? null),
                'updated_at' => $this->formatDateTime($this->membership_updated_at ?? null),
            ],
            'role_conflict' => $this->resolveRoleConflict(),
        ];
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toJSON();
        }

        try {
            return CarbonImmutable::parse($value)->toJSON();
        } catch (\Throwable) {
            return null;
        }
    }

    private function isActiveForMembership(): bool
    {
        if ($this->membership_company_id === null || $this->company_id === null) {
            return false;
        }

        return (int) $this->company_id === (int) $this->membership_company_id;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRoleConflict(): array
    {
        $roleList = $this->membership_role_list ?? null;
        $roles = $this->normalizeRoles($roleList);

        $totalCompanies = (int) ($this->membership_company_total ?? ($this->membership_company_id ? 1 : 0));
        $hasMultipleCompanies = $totalCompanies > 1;
        $hasMultipleRoles = count($roles) > 1;

        $hasBuyerRoles = $this->containsRoleCategory($roles, self::BUYER_ROLES);
        $hasSupplierRoles = $this->containsRoleCategory($roles, self::SUPPLIER_ROLES);
        $buyerSupplierConflict = $hasBuyerRoles && $hasSupplierRoles;

        $hasConflict = ($hasMultipleCompanies && $hasMultipleRoles) || $buyerSupplierConflict;

        return [
            'total_companies' => $totalCompanies,
            'distinct_roles' => $roles,
            'buyer_supplier_conflict' => $buyerSupplierConflict,
            'has_conflict' => $hasConflict,
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeRoles(?string $roleList): array
    {
        if ($roleList === null || trim($roleList) === '') {
            $role = $this->membership_role ?? $this->role ?? null;

            return $role ? [$role] : [];
        }

        $roles = array_filter(array_map(static fn (string $role): string => trim($role), explode(',', $roleList)));
        $roles = array_values(array_unique($roles));
        sort($roles);

        return $roles;
    }

    /**
     * @param list<string> $roles
     * @param list<string> $category
     */
    private function containsRoleCategory(array $roles, array $category): bool
    {
        foreach ($category as $role) {
            if (in_array($role, $roles, true)) {
                return true;
            }
        }

        return false;
    }
}
