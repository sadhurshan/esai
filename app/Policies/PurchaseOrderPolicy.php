<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\Permissions\PermissionRegistry;
use App\Support\PurchaseOrders\PurchaseOrderSupplierResolver;
use App\Support\ActivePersonaContext;

class PurchaseOrderPolicy
{
    private const SUPPLIER_ROLES = ['supplier_admin', 'supplier_estimator', 'owner'];
    private const PLATFORM_ROLES = ['platform_super', 'platform_support'];

    public function __construct(private readonly PermissionRegistry $permissionRegistry)
    {
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if ($this->hasBuyerPermission($user, $purchaseOrder, 'orders.read')) {
            return true;
        }

        if ($this->belongsToSupplier($user, $purchaseOrder)) {
            return in_array($user->role, self::SUPPLIER_ROLES, true);
        }

        return $user->isPlatformAdmin();
    }

    public function send(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->hasBuyerPermission($user, $purchaseOrder, 'orders.write');
    }

    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->send($user, $purchaseOrder);
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->hasBuyerPermission($user, $purchaseOrder, 'orders.write');
    }

    public function export(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->view($user, $purchaseOrder);
    }

    public function download(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->view($user, $purchaseOrder);
    }

    public function viewEvents(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if ($this->hasBuyerPermission($user, $purchaseOrder, 'orders.read')) {
            return true;
        }

        if ($this->belongsToSupplier($user, $purchaseOrder)) {
            return in_array($user->role, self::SUPPLIER_ROLES, true);
        }

        return $user->isPlatformAdmin();
    }

    public function acknowledge(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if ($this->belongsToSupplier($user, $purchaseOrder)) {
            return in_array($user->role, self::SUPPLIER_ROLES, true);
        }

        return $user->isPlatformAdmin();
    }

    public function createSupplierInvoice(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if (! $this->belongsToSupplier($user, $purchaseOrder)) {
            return false;
        }

        return in_array($user->role, self::SUPPLIER_ROLES, true);
    }

    private function belongsToBuyer(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->company_id !== null && (int) $user->company_id === (int) $purchaseOrder->company_id;
    }

    private function belongsToSupplier(User $user, PurchaseOrder $purchaseOrder): bool
    {
        $supplierCompanyId = PurchaseOrderSupplierResolver::resolveSupplierCompanyId($purchaseOrder);

        if ($supplierCompanyId === null) {
            return false;
        }

        if ($user->company_id !== null && (int) $supplierCompanyId === (int) $user->company_id) {
            return true;
        }

        $personaSupplierCompanyId = ActivePersonaContext::supplierCompanyId();

        return $personaSupplierCompanyId !== null && (int) $personaSupplierCompanyId === (int) $supplierCompanyId;
    }

    private function hasPlatformRole(User $user): bool
    {
        return in_array($user->role, self::PLATFORM_ROLES, true);
    }

    private function hasBuyerPermission(User $user, PurchaseOrder $purchaseOrder, string $permission): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        if (! $this->belongsToBuyer($user, $purchaseOrder)) {
            return false;
        }

        if ($purchaseOrder->company_id === null) {
            return false;
        }

        return $this->permissionRegistry->userHasAny($user, [$permission], (int) $purchaseOrder->company_id);
    }
}
