<?php

namespace App\Services\Auth;

use App\Models\Company;
use App\Models\SupplierContact;
use App\Models\User;
use App\Support\CompanyContext;
use BackedEnum;
use Illuminate\Support\Collection;

class PersonaResolver
{
    private const BUYER_ROLES = [
        'owner',
        'buyer_admin',
        'buyer_member',
        'buyer_requester',
        'finance',
    ];

    public function resolve(User $user): array
    {
        $buyers = $this->buyerPersonas($user);
        $suppliers = $this->supplierPersonas($user);

        return array_values(array_merge($buyers, $suppliers));
    }

    /**
     * @param  array<int, array<string, mixed>>  $personas
     * @return array<string, mixed>|null
     */
    public function determineActivePersona(User $user, array $personas): ?array
    {
        if ($personas === []) {
            return null;
        }

        $user->loadMissing('company');
        $company = $user->company;

        if ($company !== null && $company->start_mode === 'supplier') {
            $supplierPreferred = $this->findPersona($personas, static function (array $persona): bool {
                return $persona['type'] === 'supplier';
            });

            if ($supplierPreferred !== null) {
                return $supplierPreferred;
            }
        }

        $preferred = null;

        if ($user->company_id !== null) {
            $preferred = $this->findPersona($personas, static function (array $persona) use ($user): bool {
                return $persona['type'] === 'buyer'
                    && (int) $persona['company_id'] === (int) $user->company_id;
            });
        }

        if ($preferred === null && $user->default_supplier_id !== null) {
            $preferred = $this->findPersona($personas, static function (array $persona) use ($user): bool {
                if ($persona['type'] !== 'supplier') {
                    return false;
                }

                return isset($persona['supplier_id'])
                    && (int) $persona['supplier_id'] === (int) $user->default_supplier_id;
            });
        }

        if ($preferred !== null) {
            return $preferred;
        }

        return $this->fallbackPersona($personas);
    }

    private function buyerPersonas(User $user): array
    {
        $memberships = $user->companies()
            ->withPivot(['role', 'is_default', 'last_used_at'])
            ->get();

        return $memberships
            ->filter(fn (Company $company) => $this->isBuyerRole($company->pivot?->role))
            ->map(function (Company $company): array {
                $status = $company->status;
                if ($status instanceof BackedEnum) {
                    $status = $status->value;
                }

                $supplierStatus = $company->supplier_status;
                if ($supplierStatus instanceof BackedEnum) {
                    $supplierStatus = $supplierStatus->value;
                }

                $role = $company->pivot?->role;

                return [
                    'key' => $this->buildKey('buyer', $company->id),
                    'type' => 'buyer',
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                    'company_status' => $status,
                    'company_supplier_status' => $supplierStatus,
                    'role' => $role,
                    'is_default' => (bool) ($company->pivot?->is_default ?? false),
                ];
            })
            ->values()
            ->all();
    }

    private function supplierPersonas(User $user): array
    {
        $supplierRole = $this->supplierRoleForUser($user);

        /** @var Collection<int, SupplierContact> $contacts */
        $contacts = CompanyContext::bypass(function () use ($user) {
            return SupplierContact::query()
                ->where('user_id', $user->id)
                ->with(['company', 'supplier.company'])
                ->get();
        });

        return $contacts
            ->filter(static fn (SupplierContact $contact): bool => $contact->supplier !== null)
            ->map(function (SupplierContact $contact) use ($user, $supplierRole): array {
                $supplier = $contact->supplier;
                $supplierCompany = $supplier?->company;
                $buyerCompany = $contact->company;

                return [
                    'key' => $this->buildKey('supplier', (int) $contact->company_id, $supplier?->id),
                    'type' => 'supplier',
                    'company_id' => $contact->company_id,
                    'company_name' => $buyerCompany?->name,
                    'company_status' => $buyerCompany?->status instanceof BackedEnum ? $buyerCompany->status->value : $buyerCompany?->status,
                    'supplier_id' => $supplier?->id,
                    'supplier_name' => $supplier?->name,
                    'supplier_company_id' => $supplierCompany?->id,
                    'supplier_company_name' => $supplierCompany?->name,
                    'is_default' => $user->default_supplier_id !== null
                        && $supplier !== null
                        && (int) $user->default_supplier_id === (int) $supplier->id,
                    'role' => $supplierRole,
                ];
            })
            ->values()
            ->all();
    }

    private function buildKey(string $type, int $companyId, ?int $supplierId = null): string
    {
        return $supplierId === null
            ? sprintf('%s:%d', $type, $companyId)
            : sprintf('%s:%d:%d', $type, $companyId, $supplierId);
    }

    private function isBuyerRole(?string $role): bool
    {
        if ($role === null) {
            return false;
        }

        return in_array($role, self::BUYER_ROLES, true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $personas
     * @return array<string, mixed>|null
     */
    private function fallbackPersona(array $personas): ?array
    {
        $defaultBuyer = $this->findPersona($personas, static function (array $persona): bool {
            return $persona['type'] === 'buyer' && ! empty($persona['is_default']);
        });

        if ($defaultBuyer !== null) {
            return $defaultBuyer;
        }

        $anyBuyer = $this->findPersona($personas, static function (array $persona): bool {
            return $persona['type'] === 'buyer';
        });

        if ($anyBuyer !== null) {
            return $anyBuyer;
        }

        return $personas[0] ?? null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $personas
     */
    private function findPersona(array $personas, callable $callback): ?array
    {
        foreach ($personas as $persona) {
            if ($callback($persona) === true) {
                return $persona;
            }
        }

        return null;
    }

    private function supplierRoleForUser(User $user): ?string
    {
        $role = $user->role;

        if ($role === null) {
            return null;
        }

        if (in_array($role, ['owner', 'supplier_admin', 'supplier_estimator'], true)) {
            return $role;
        }

        return null;
    }
}
